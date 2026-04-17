<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Models\BonusTask;
use App\Models\PromotionCode;
use App\Models\PromotionCodeClaim;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromotionCodeService
{
    protected BonusTaskService $bonusTaskService;

    public function __construct()
    {
        $this->bonusTaskService = new BonusTaskService;
    }

    /**
     * 兑换 promotion code，当前仅支持 bonus_type=bonus_task（由 bonus_config 生成 BonusTask）。
     *
     * bonus_config 必填：cap_bonus、need_wager；可选：currency（缺省用 config app.currency）、base_bonus、last_bonus、bonus_name、expired_at。
     * target_type=users 时需在 bonus_config 中提供 eligible_user_ids（用户 id 数组）。
     *
     * @return array{claim: PromotionCodeClaim, bonus_task: BonusTask}
     */
    public function claim(int $userId, string $code): array
    {
        $trimmed = trim($code);
        if ($trimmed === '') {
            throw new Exception(ErrorCode::VALIDATION_ERROR, 'Code is required');
        }

        try {
            return DB::transaction(function () use ($userId, $trimmed) {
                $promo = PromotionCode::query()
                    ->whereRaw('LOWER(code) = ?', [Str::lower($trimmed)])
                    ->lockForUpdate()
                    ->first();

                if (! $promo) {
                    throw new Exception(ErrorCode::PROMOTION_CODE_NOT_FOUND);
                }

                if ($promo->isInactiveStatus()) {
                    throw new Exception(ErrorCode::PROMOTION_CODE_INACTIVE);
                }

                if ($promo->isGloballyExpired()) {
                    throw new Exception(ErrorCode::PROMOTION_CODE_EXPIRED);
                }

                if (! $this->isUserEligible($promo, $userId)) {
                    throw new Exception(ErrorCode::PROMOTION_CODE_NOT_ELIGIBLE);
                }

                $existingClaim = PromotionCodeClaim::query()
                    ->where('promotion_code_id', $promo->id)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if ($existingClaim?->status === PromotionCodeClaim::STATUS_COMPLETED) {
                    throw new Exception(ErrorCode::PROMOTION_CODE_ALREADY_CLAIMED);
                }

                if (! $existingClaim) {
                    if ($promo->isExhaustedStatus() || $promo->claimed_count >= $promo->times) {
                        throw new Exception(ErrorCode::PROMOTION_CODE_EXHAUSTED);
                    }

                    $claim = PromotionCodeClaim::create([
                        'user_id' => $userId,
                        'promotion_code_id' => $promo->id,
                        'status' => PromotionCodeClaim::STATUS_PENDING,
                        'claimed_at' => now(),
                    ]);
                } elseif ($existingClaim->status === PromotionCodeClaim::STATUS_PENDING) {
                    $claim = $existingClaim;
                } else {
                    throw new Exception(ErrorCode::PROMOTION_CODE_ALREADY_CLAIMED);
                }

                $task = match ($promo->bonus_type) {
                    PromotionCode::BONUS_TYPE_BONUS_TASK => $this->createBonusTaskFromPromotion($promo, $userId),
                    default => throw new Exception(ErrorCode::PROMOTION_CODE_UNSUPPORTED_BONUS_TYPE),
                };

                $claim->status = PromotionCodeClaim::STATUS_COMPLETED;
                $claim->save();

                $newCount = (int) $promo->claimed_count + 1;
                $promo->claimed_count = $newCount;
                if ($newCount >= $promo->times) {
                    $promo->status = PromotionCode::STATUS_EXHAUSTED;
                }
                $promo->save();

                return [
                    'claim' => $claim,
                    'bonus_task' => $task,
                ];
            });
        } catch (QueryException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, 'UNIQUE constraint failed')) {
                throw new Exception(ErrorCode::PROMOTION_CODE_ALREADY_CLAIMED);
            }
            throw $e;
        }
    }

    protected function isUserEligible(PromotionCode $promo, int $userId): bool
    {
        if ($promo->targetsAllUsers()) {
            return true;
        }

        if ($promo->target_type === PromotionCode::TARGET_TYPE_USERS) {
            $ids = $promo->bonus_config['eligible_user_ids'] ?? null;
            if (! is_array($ids) || $ids === []) {
                return false;
            }

            return in_array($userId, array_map('intval', $ids), true);
        }

        return false;
    }

    /**
     * 组装任务数据并仅通过 {@see BonusTaskService::createTask()} 创建 BonusTask（统一过期时间、通知等逻辑）。
     */
    protected function createBonusTaskFromPromotion(PromotionCode $promo, int $userId): BonusTask
    {
        $cfg = $promo->bonus_config;
        if (! is_array($cfg)) {
            throw new Exception(ErrorCode::PROMOTION_CODE_INVALID_BONUS_CONFIG);
        }

        foreach (['cap_bonus', 'need_wager'] as $key) {
            if (! array_key_exists($key, $cfg)) {
                throw new Exception(ErrorCode::PROMOTION_CODE_INVALID_BONUS_CONFIG);
            }
        }

        $cap = (float) $cfg['cap_bonus'];
        $needWager = (float) $cfg['need_wager'];

        $currencyRaw = isset($cfg['currency']) ? trim((string) $cfg['currency']) : '';
        $currency = $currencyRaw !== ''
            ? $currencyRaw
            : (string) config('app.currency', 'USD');

        if ($cap < 0 || $needWager < 0) {
            throw new Exception(ErrorCode::PROMOTION_CODE_INVALID_BONUS_CONFIG);
        }

        $base = isset($cfg['base_bonus']) ? (float) $cfg['base_bonus'] : $cap;
        $last = isset($cfg['last_bonus']) ? (float) $cfg['last_bonus'] : $cap;
        $bonusName = isset($cfg['bonus_name']) ? (string) $cfg['bonus_name'] : $promo->name;

        $taskNo = 'PROMO_'.$promo->id.'_'.$userId;
        if (strlen($taskNo) > 50) {
            $taskNo = substr($taskNo, 0, 50);
        }

        $taskData = [
            'user_id' => $userId,
            'task_no' => $taskNo,
            'bonus_name' => $bonusName,
            'cap_bonus' => $cap,
            'base_bonus' => $base,
            'last_bonus' => $last,
            'need_wager' => $needWager,
            'wager' => 0,
            'status' => BonusTask::STATUS_PENDING,
            'currency' => $currency,
        ];

        if (! empty($cfg['expired_at'])) {
            $taskData['expired_at'] = Carbon::parse((string) $cfg['expired_at']);
        }

        $existingTask = BonusTask::query()
            ->where('user_id', $userId)
            ->where('task_no', $taskNo)
            ->first();

        if ($existingTask) {
            $this->bonusTaskService->activateNextPendingTask($userId);

            return $existingTask->fresh();
        }

        // 经 BonusTaskService::createTask（含默认 expired_at、发通知等），勿直接 BonusTask::create
        $task = $this->bonusTaskService->createTask($taskData);
        $this->bonusTaskService->activateNextPendingTask($userId);

        return $task->fresh();
    }
}
