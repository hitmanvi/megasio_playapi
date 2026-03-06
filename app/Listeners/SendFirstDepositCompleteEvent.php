<?php

namespace App\Listeners;

use App\Events\FirstDepositCompleted;
use App\Services\AgentService;
use App\Services\FacebookConversionsService;
use App\Services\KochavaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendFirstDepositCompleteEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(FirstDepositCompleted $event): void
    {
        $deposit = $event->deposit->loadMissing('user');
        $link = AgentService::getAgentLinkForUser($deposit->user);
        $deviceInfo = KochavaService::deviceInfoFromDeposit($deposit);

        // Kochava
        if (!empty($deviceInfo['kochava_device_id']) || !empty($deviceInfo['device_ids'])) {
            $kochava = new KochavaService($link);
            $kochava->sendEvent('first_purchase', [
                'user_id' => $deposit->user->uid,
                'order_id' => $deposit->order_no,
                'content_id' => $deposit->order_no,
                'currency' => $deposit->currency,
                'price' => (float) $deposit->amount,
                'name' => 'First Deposit',
            ], $deviceInfo);
        }

        // Facebook
        $facebook = new FacebookConversionsService($link);
        if ($facebook->isEnabled()) {
            $userData = FacebookConversionsService::userDataFromDeposit($deposit, $deviceInfo);
            $userData['event_time'] = $deposit->completed_at?->timestamp ?? time();
            $facebook->sendEvent(
                'FirstDeposit',
                $userData,
                ['currency' => strtolower($deposit->currency), 'value' => (float) $deposit->amount],
                'first_purchase_' . $deposit->order_no
            );
        }
    }
}
