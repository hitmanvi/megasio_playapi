<?php

namespace App\Listeners;

use App\Events\FirstDepositCompleted;
use App\Services\AgentService;
use App\Services\FacebookConversionsService;
use App\Services\KochavaService;
use App\Services\TikTokEventsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendFirstDepositCompleteEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(FirstDepositCompleted $event): void
    {
        $deposit = $event->deposit->loadMissing('user');
        $link = AgentService::getAgentLinkForUser($deposit->user);
        $deviceInfo = $deposit->getDeviceInfoForEvent();

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

        $tiktok = new TikTokEventsService($link);
        if ($tiktok->isEnabled()) {
            $ttUser = TikTokEventsService::userDataFromDeposit($deposit, $deviceInfo);
            $amount = (float) $deposit->amount;
            $currency = strtoupper((string) $deposit->currency);
            $eventName = 'FirstDeposit';
            $properties = [
                'value' => $amount,
                'currency' => $currency,
                'content_type' => 'product',
                'content_id' => $eventName,
                'quantity' => 1,
            ];
            $context = [];
            if (! empty($deviceInfo['event_source_url'])) {
                $context['event_source_url'] = $deviceInfo['event_source_url'];
            }
            $tiktok->sendEvent(
                'FirstDeposit',
                $ttUser,
                $properties,
                'first_purchase_'.$deposit->order_no,
                $deposit->completed_at?->timestamp,
                $context
            );
        }
    }
}
