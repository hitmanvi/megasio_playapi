<?php

namespace App\Listeners;

use App\Events\DepositCompleted;
use App\Services\KochavaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendKochavaDepositCompleteEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DepositCompleted $event): void
    {
        $deposit = $event->deposit;
        $deviceInfo = KochavaService::deviceInfoFromDeposit($deposit);
        if (empty($deviceInfo['kochava_device_id']) && empty($deviceInfo['device_ids'])) {
            return;
        }
        $service = new KochavaService();
        $service->sendEvent('purchase', [
            'user_id' => $deposit->user_id,
            'order_no' => $deposit->order_no,
            'currency' => $deposit->currency,
            'amount' => (float) $deposit->amount,
            'event_id' => 'purchase_' . $deposit->order_no,
        ], $deviceInfo);
    }
}
