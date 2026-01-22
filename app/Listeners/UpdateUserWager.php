<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Services\InvitationRewardService;
use App\Services\UserWagerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateUserWager implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * 队列连接名称（使用默认连接则不需要指定）
     */
    // public $connection = 'database';

    /**
     * 队列名称（使用默认队列则不需要指定）
     */
    // public $queue = 'default';

    protected UserWagerService $wagerService;
    protected InvitationRewardService $rewardService;

    public function __construct()
    {
        $this->wagerService = new UserWagerService();
        $this->rewardService = new InvitationRewardService();
    }

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

        // 计算佣金（会判断游戏类型，如果不是 slot 类型返回 0）
        $reward = $this->rewardService->calculateReward(
            (float) $order->amount,
            $order->game
        );

        // 如果佣金为 0（即不是 slot 类型），则不更新 wager
        if ($reward <= 0) {
            return;
        }

        // 更新用户的 wager（使用订单的 amount）
        $this->wagerService->addWager(
            $order->user_id,
            (float) $order->amount
        );
    }
}
