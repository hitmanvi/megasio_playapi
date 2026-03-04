<?php

namespace App\Listeners;

use App\Events\DepositCompleted;
use App\Services\AgentService;
use App\Services\FacebookConversionsService;
use App\Services\KochavaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDepositCompleteEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DepositCompleted $event): void
    {
        $deposit = $event->deposit->loadMissing('user');
        $agent = AgentService::getAgentForUser($deposit->user);
        $deviceInfo = KochavaService::deviceInfoFromDeposit($deposit);

        // Kochava
        if (!empty($deviceInfo['kochava_device_id']) || !empty($deviceInfo['device_ids'])) {
            $kochava = new KochavaService($agent);
            $kochava->sendEvent('purchase', [
                'user_id' => $deposit->user_id,
                'order_no' => $deposit->order_no,
                'currency' => $deposit->currency,
                'amount' => (float) $deposit->amount,
                'event_id' => 'purchase_' . $deposit->order_no,
            ], $deviceInfo);
        }

        // Facebook
        $facebook = new FacebookConversionsService($agent);
        if ($facebook->isEnabled()) {
            $userData = FacebookConversionsService::userDataFromDeposit($deposit, $deviceInfo);
            $userData['event_time'] = $deposit->completed_at?->timestamp ?? time();
            $facebook->sendEvent(
                'Purchase',
                $userData,
                ['currency' => strtolower($deposit->currency), 'value' => (float) $deposit->amount],
                'purchase_' . $deposit->order_no
            );
        }
    }
}
