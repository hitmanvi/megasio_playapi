<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Services\WeeklyCashbackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateWeeklyCashbackOnOrderCompleted implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        $service = new WeeklyCashbackService();
        if (!$service->orderSupportsCashback($order)) {
            return;
        }

        $service->addToBuffer($order);
    }
}
