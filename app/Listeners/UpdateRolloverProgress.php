<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\Rollover;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateRolloverProgress implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        // 更新 rollover 进度（对所有订单都更新，不限制游戏类型或货币类型）
        $this->updateRolloverProgress($order->user_id, (float) $order->amount, $order->currency);
    }

    /**
     * 更新 rollover 进度
     */
    protected function updateRolloverProgress(int $userId, float $wagerAmount, string $currency): void
    {
        // 只查找当前激活的 rollover（状态为 active，且货币匹配）
        $activeRollover = Rollover::where('user_id', $userId)
            ->where('currency', $currency)
            ->where('status', Rollover::STATUS_ACTIVE)
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$activeRollover) {
            // 如果没有激活的 rollover，尝试激活下一个 pending 的
            $nextRollover = $this->activateNextRollover($userId, $currency);
            if (!$nextRollover) {
                // 没有待激活的 rollover，直接返回
                return;
            }
            // 使用新激活的 rollover
            $activeRollover = $nextRollover;
        }

        // 更新当前激活的 rollover 的流水
        $activeRollover->current_wager += $wagerAmount;
        $activeRollover->save();

        // 检查是否已完成
        $wasCompleted = $activeRollover->checkCompletion();

        // 如果当前 rollover 已完成，激活下一个 pending 的 rollover
        if ($wasCompleted) {
            $this->activateNextRollover($userId, $currency);
        }
    }

    /**
     * 激活下一个 pending 的 rollover
     *
     * @param int $userId
     * @param string $currency
     * @return Rollover|null
     */
    protected function activateNextRollover(int $userId, string $currency): ?Rollover
    {
        // 查找下一个待激活的 rollover（按创建时间排序）
        $nextRollover = Rollover::where('user_id', $userId)
            ->where('currency', $currency)
            ->where('status', Rollover::STATUS_PENDING)
            ->orderBy('created_at', 'asc')
            ->first();

        if ($nextRollover) {
            $nextRollover->status = Rollover::STATUS_ACTIVE;
            $nextRollover->save();
            return $nextRollover;
        }

        return null;
    }
}