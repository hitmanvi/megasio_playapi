<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Jobs\RecordUserRecentGameJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RecordUserRecentGame implements ShouldQueue
{
    use InteractsWithQueue;

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
            
            // 派发到队列异步处理
            RecordUserRecentGameJob::dispatch($order->user_id, $order->game_id, $multiplier);
        }
    }
}
