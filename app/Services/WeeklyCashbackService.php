<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception as AppException;
use App\Models\Order;
use App\Models\User;
use App\Models\WeeklyCashback;
use App\Services\BalanceService;
use App\Services\NotificationService;
use App\Services\VipService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class WeeklyCashbackService
{
    private const BUFFER_KEY_PREFIX = 'weekly_cashback_buffer:';

    /**
     * 检查 VIP 配置中 weekly_cashback 是否开启
     */
    public function isWeeklyCashbackEnabled(): bool
    {
        $vipSetting = (new SettingService())->getValue('vip', []);
        return ($vipSetting['weekly_cashback']['enabled'] ?? false) === true;
    }

    /**
     * 将日期转换为 period（ISO 年*100+周数）
     */
    public function dateToPeriod(Carbon|string $date): int
    {
        $dt = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $dt->isoWeekYear() * 100 + $dt->isoWeek();
    }

    /**
     * 判断订单是否支持 cashback（留白，待实现）
     */
    public function orderSupportsCashback(Order $order): bool
    {
        // TODO: 实现判断逻辑
        $settingService = new SettingService();
        $vipSetting = $settingService->getValue('vip', []);
        $supportedCategories = $vipSetting['supported_game_categories'] ?? [];
        if (!is_array($supportedCategories)) {
            $supportedCategories = [];
        }
        if (in_array($order->game->category_id, $supportedCategories)) {
            return true;
        }
        return false;
    }

    /**
     * 将订单数据写入缓冲（Redis），减少 DB 写入
     */
    public function addToBuffer(Order $order): void
    {
        if (!$this->isWeeklyCashbackEnabled()) {
            return;
        }
        $date = $order->finished_at ?? $order->created_at ?? now();
        $period = $this->dateToPeriod($date);
        $key = self::BUFFER_KEY_PREFIX . $order->user_id . ':' . $period . ':' . $order->currency;

        try {
            Redis::hincrbyfloat($key, 'wager', (float) $order->amount);
            Redis::hincrbyfloat($key, 'payout', (float) $order->payout);
            Redis::expire($key, 86400 * 7); // 7 天过期，防止残留
        } catch (\Throwable $e) {
            // Redis 不可用时回退到直接写 DB
            $this->updateCashbackFromOrder($order);
        }
    }

    /**
     * 将缓冲数据刷入数据库
     */
    public function flushBuffer(): int
    {
        $flushed = 0;

        try {
            $keys = Redis::keys(self::BUFFER_KEY_PREFIX . '*');
            foreach ($keys as $key) {
                $data = Redis::hgetall($key);
                if (empty($data)) {
                    Redis::del($key);
                    continue;
                }
                $parsed = $this->parseBufferKey($key);
                if ($parsed) {
                    $this->applyBufferToDb($parsed['user_id'], $parsed['period'], $parsed['currency'], (float) ($data['wager'] ?? 0), (float) ($data['payout'] ?? 0));
                    $flushed++;
                }
                Redis::del($key);
            }
        } catch (\Throwable $e) {
            // Redis 异常时静默跳过，下次再刷
        }

        return $flushed;
    }

    private function parseBufferKey(string $key): ?array
    {
        $pos = strpos($key, self::BUFFER_KEY_PREFIX);
        $suffix = $pos !== false ? substr($key, $pos + strlen(self::BUFFER_KEY_PREFIX)) : substr($key, strlen(self::BUFFER_KEY_PREFIX));
        $parts = explode(':', $suffix);
        if (count($parts) !== 3) {
            return null;
        }
        return [
            'user_id' => (int) $parts[0],
            'period' => (int) $parts[1],
            'currency' => $parts[2],
        ];
    }

    private function applyBufferToDb(int $userId, int $period, string $currency, float $wager, float $payout): void
    {
        if (!$this->isWeeklyCashbackEnabled()) {
            return;
        }
        if ($wager <= 0 && $payout <= 0) {
            return;
        }

        $cashback = WeeklyCashback::firstOrCreate(
            [
                'user_id' => $userId,
                'period' => $period,
                'currency' => $currency,
            ],
            [
                'wager' => 0,
                'payout' => 0,
                'status' => WeeklyCashback::STATUS_ACTIVE,
                'rate' => 0,
                'amount' => 0,
            ]
        );

        if ($cashback->status !== WeeklyCashback::STATUS_ACTIVE) {
            return;
        }

        $cashback->wager += $wager;
        $cashback->payout += $payout;
        $cashback->save();
    }

    /**
     * 从用户 VIP 等级的 benefits 中获取 weekly_cashback 返现比例
     * 优先读取 benefits.weekly_cashback（可为数字或 { rate: x }），否则回退到 benefits.cashback_rate
     * rate 为百分数的分子，如 5 表示 5%
     *
     * @param int $userId 用户 ID
     * @return float 返现比例（百分数的分子），如 5 表示 5%
     */
    public function getWeeklyCashbackRateForUser(int $userId): float
    {
        $user = User::with('vip')->find($userId);
        if (!$user || !$user->vip) {
            return 0;
        }

        $levelInfo = (new VipService())->getLevelInfo($user->vip->level);
        $benefits = $levelInfo['benefits'] ?? [];
        if (empty($benefits)) {
            return 0;
        }

        $weeklyCashback = $benefits['weekly_cashback'] ?? null;
        if ($weeklyCashback !== null) {
            if (is_numeric($weeklyCashback)) {
                return (float) $weeklyCashback;
            }
        }

        return 0;
    }

    /**
     * 计算指定周期的 weekly cashback（将 active 记录计算 rate/amount 并标记为 claimable）
     * 每周一 02:00 执行，计算上周数据
     * rate 从用户 VIP 等级的 benefits.weekly_cashback 获取
     *
     * @param int $period ISO 年*100+周数
     * @return int 处理的记录数
     */
    public function calculateAndFinalizeForPeriod(int $period): int
    {
        if (!$this->isWeeklyCashbackEnabled()) {
            return 0;
        }

        // 先把该周期前的 claimable 全部标记为 expired
        WeeklyCashback::where('period', '<', $period)
            ->where('status', WeeklyCashback::STATUS_CLAIMABLE)
            ->update(['status' => WeeklyCashback::STATUS_EXPIRED]);

        $records = WeeklyCashback::where('period', $period)
            ->where('status', WeeklyCashback::STATUS_ACTIVE)
            ->get();

        $count = 0;
        foreach ($records as $cashback) {
            $rate = $this->getWeeklyCashbackRateForUser($cashback->user_id);
            // rate 为百分数的分子（如 5 表示 5%）
            $netWager = (float) $cashback->wager - (float) $cashback->payout;
            $amount = max(0, $netWager * $rate / 100);

            $cashback->rate = $rate; // 统一存储为百分数分子
            $cashback->amount = $amount;
            $cashback->status = $amount > 0 ? WeeklyCashback::STATUS_CLAIMABLE : WeeklyCashback::STATUS_CLAIMED;
            if ($amount <= 0) {
                $cashback->claimed_at = now();
            }
            $cashback->save();

            if ($amount > 0) {
                (new NotificationService())->createWeeklyCashbackNotification(
                    $cashback->user_id,
                    $amount,
                    $cashback->currency,
                    $cashback->no,
                    $cashback->period
                );
            }
            $count++;
        }

        return $count;
    }

    /**
     * 获取用户已领取的 weekly cashback 总金额
     */
    public function getClaimedTotalForUser(int $userId): float
    {
        return (float) WeeklyCashback::where('user_id', $userId)
            ->where('status', WeeklyCashback::STATUS_CLAIMED)
            ->sum('amount');
    }

    /**
     * 获取用户上周可领取的 cashback（单个）；同时将非上周的 claimable 标记为过期
     */
    public function getClaimableForUser(int $userId): ?WeeklyCashback
    {
        return WeeklyCashback::where('user_id', $userId)
            ->where('status', WeeklyCashback::STATUS_CLAIMABLE)
            ->first();
    }

    /**
     * 领取 weekly cashback（通过 no 查找）
     */
    public function claim(int $userId, string $no): array
    {
        $cashback = WeeklyCashback::where('user_id', $userId)->where('no', $no)->first();

        if (!$cashback) {
            throw new AppException(ErrorCode::WEEKLY_CASHBACK_NOT_FOUND);
        }

        if ($cashback->status !== WeeklyCashback::STATUS_CLAIMABLE) {
            throw new AppException(ErrorCode::WEEKLY_CASHBACK_NOT_CLAIMABLE);
        }

        $amount = (float) $cashback->amount;
        if ($amount <= 0) {
            throw new AppException(ErrorCode::WEEKLY_CASHBACK_NO_AMOUNT);
        }

        $balanceService = new BalanceService();
        $result = $balanceService->weeklyCashbackReward(
            $userId,
            $cashback->currency,
            $amount,
            $cashback->id,
            $cashback->period
        );

        $cashback->status = WeeklyCashback::STATUS_CLAIMED;
        $cashback->claimed_at = now();
        $cashback->save();

        return [
            'cashback' => $cashback,
            'claim_amount' => $amount,
            'currency' => $cashback->currency,
        ];
    }

    /**
     * 根据订单更新对应的 weekly cashback（仅当 status 为 active 时累加，直接写 DB）
     */
    public function updateCashbackFromOrder(Order $order): void
    {
        if (!$this->isWeeklyCashbackEnabled()) {
            return;
        }
        $date = $order->finished_at ?? $order->created_at ?? now();
        $period = $this->dateToPeriod($date);

        $this->applyBufferToDb(
            $order->user_id,
            $period,
            $order->currency,
            (float) $order->amount,
            (float) $order->payout
        );
    }
}
