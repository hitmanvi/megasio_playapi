<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception as AppException;
use App\Models\Order;
use App\Models\WeeklyCashback;
use App\Services\BalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class WeeklyCashbackService
{
    private const BUFFER_KEY_PREFIX = 'weekly_cashback_buffer:';

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
        return false;
    }

    /**
     * 将订单数据写入缓冲（Redis），减少 DB 写入
     */
    public function addToBuffer(Order $order): void
    {
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
     * 获取用户上周可领取的 cashback（单个）；同时将非上周的 claimable 标记为过期
     */
    public function getClaimableForUser(int $userId): ?WeeklyCashback
    {
        $lastWeekPeriod = $this->dateToPeriod(Carbon::now()->subWeek());

        // 将非上周的 claimable 标记为过期
        WeeklyCashback::where('user_id', $userId)
            ->where('status', WeeklyCashback::STATUS_CLAIMABLE)
            ->where('period', '!=', $lastWeekPeriod)
            ->update(['status' => WeeklyCashback::STATUS_EXPIRED]);

        return WeeklyCashback::where('user_id', $userId)
            ->where('period', $lastWeekPeriod)
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
