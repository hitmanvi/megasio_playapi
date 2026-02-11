<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\Rollover;
use App\Services\InvitationRewardService;
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

        // 检查订单是否有游戏信息
        if (!$order->game_id) {
            return;
        }

        // 确保加载游戏和分类关系
        if (!$order->relationLoaded('game')) {
            $order->load('game.category');
        }

        if (!$order->game) {
            return;
        }

        // 检查订单货币是否为应用默认货币
        $appCurrency = config('app.currency', 'USD');
        if ($order->currency !== $appCurrency) {
            // 订单货币不是应用默认货币，跳过
            return;
        }

        // 计算佣金（会判断游戏类型，如果不是 slot 类型返回 0）
        $rewardService = new InvitationRewardService();
        $reward = $rewardService->calculateReward(
            (float) $order->amount,
            $order->game
        );

        // 如果佣金为 0（即不是 slot 类型），则不更新 rollover
        if ($reward <= 0) {
            return;
        }

        // 更新 rollover 进度
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
