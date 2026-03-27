<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\User;
use App\Services\UserVipService;
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

        if ($order->status !== Order::STATUS_COMPLETED) {
            return;
        }

        $user = User::query()->find($order->user_id);
        if (!$user) {
            return;
        }

        $expToAdd = UserVipService::calculateExpFromOrder((float) $order->amount, $order->currency);

        if ($expToAdd > 0) {
            (new VipService())->addExp($user, $expToAdd);
        }
    }

    /**
     * 检查游戏分类是否在支持列表中
     *
     * @param  \App\Models\Game  $game
     */
    protected function isSupportedGameCategory($game): bool
    {
        return true;
    }
}
