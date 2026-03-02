<?php

namespace App\Listeners;

use App\Events\DepositCompleted;
use App\Services\FacebookConversionsService;
use App\Services\KochavaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendFacebookDepositCompleteEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DepositCompleted $event): void
    {
        $deposit = $event->deposit->loadMissing('user');
        $deviceInfo = KochavaService::deviceInfoFromDeposit($deposit);
        $userData = FacebookConversionsService::userDataFromDeposit($deposit, $deviceInfo);
        $userData['event_time'] = $deposit->completed_at?->timestamp ?? time();

        $service = new FacebookConversionsService();
        $service->sendEvent(
            'purchase',
            $userData,
            [
                'currency' => strtolower($deposit->currency),
                'value' => (float) $deposit->amount,
            ],
            'purchase_' . $deposit->order_no
        );
    }
}
