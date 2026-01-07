<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\UserRecentGame;

class RecordUserRecentGame
{
    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;
        
        if ($order->user_id && $order->game_id) {
            // 计算奖励倍数: payout / amount
            $multiplier = 0;
            if ($order->amount > 0) {
                $multiplier = (float) $order->payout / (float) $order->amount;
            }
            
            UserRecentGame::recordPlay($order->user_id, $order->game_id, $multiplier);
        }
    }
}
