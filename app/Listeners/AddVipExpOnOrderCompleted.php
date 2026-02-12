<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\UserVip;
use App\Services\SettingService;
use App\Services\VipService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AddVipExpOnOrderCompleted implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        // 只处理已完成的订单
        if ($order->status !== Order::STATUS_COMPLETED) {
            return;
        }

        // 检查订单是否有游戏信息
        if (!$order->game_id) {
            return;
        }

        // 确保加载游戏关系
        if (!$order->relationLoaded('game')) {
            $order->load('game');
        }

        if (!$order->game) {
            return;
        }

        // 检查游戏类型是否符合配置要求
        if (!$this->isSupportedGameCategory($order->game)) {
            return;
        }

        // 获取或创建用户VIP记录
        $userVip = UserVip::firstOrCreate(
            ['user_id' => $order->user_id],
            [
                'level' => VipService::DEFAULT_LEVEL,
                'exp' => 0,
            ]
        );

        // 计算应获得的经验值
        $expToAdd = UserVip::calculateExpFromOrder((float) $order->amount, $order->currency);

        if ($expToAdd > 0) {
            // 增加经验值（会自动检查等级升级）
            $userVip->addExp($expToAdd);
        }
    }

    /**
     * 检查游戏分类是否在支持列表中
     *
     * @param \App\Models\Game $game
     * @return bool
     */
    protected function isSupportedGameCategory($game): bool
    {
        return true;
    }
}
