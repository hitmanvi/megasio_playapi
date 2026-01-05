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
            UserRecentGame::recordPlay($order->user_id, $order->game_id);
        }
    }
}

