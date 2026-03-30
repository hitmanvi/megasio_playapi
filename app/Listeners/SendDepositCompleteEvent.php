<?php

namespace App\Listeners;

use App\Events\DepositCompleted;
use App\Services\AgentService;
use App\Services\CustomerIOService;
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
        $link = AgentService::getAgentLinkForUser($deposit->user);
        $deviceInfo = $deposit->getDeviceInfoForEvent();

        // Kochava
        if (!empty($deviceInfo['kochava_device_id']) || !empty($deviceInfo['device_ids'])) {
            $kochava = new KochavaService($link);
            $kochava->sendEvent('purchase', [
                'user_id' => $deposit->user->uid,
                'order_id' => $deposit->order_no,
                'content_id' => $deposit->order_no,
                'currency' => $deposit->currency,
                'price' => (float) $deposit->amount,
                'name' => 'Deposit',
            ], $deviceInfo);
        }

        // Facebook
        $facebook = new FacebookConversionsService($link);
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

        app(CustomerIOService::class)->sendEvent(
            $deposit->user,
            'purchase',
        );
    }
}
