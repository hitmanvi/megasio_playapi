<?php

namespace App\Listeners;

use App\Events\DepositCreated;
use App\Services\KochavaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendKochavaDepositCreateEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DepositCreated $event): void
    {
        $deposit = $event->deposit;
        $deviceInfo = $event->deviceInfo;
        if (empty($deviceInfo['kochava_device_id']) && empty($deviceInfo['device_ids'] ?? [])) {
            return;
        }
        $service = new KochavaService();
        $service->sendEvent('DepositCreate', [
            'user_id' => $deposit->user_id,
            'order_no' => $deposit->order_no,
            'currency' => $deposit->currency,
            'amount' => (float) $deposit->amount,
        ], $deviceInfo);
    }
}
