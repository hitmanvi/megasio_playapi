<?php

namespace App\Listeners;

use App\Events\DepositCreated;
use App\Services\AgentService;
use App\Services\CustomerIOService;
use App\Services\FacebookConversionsService;
use App\Services\KochavaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDepositCreateEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DepositCreated $event): void
    {
        $deposit = $event->deposit->loadMissing('user');
        $link = AgentService::getAgentLinkForUser($deposit->user);
        $deviceInfo = $event->deviceInfo;

        // Kochava
        // if (!empty($deviceInfo['kochava_device_id']) || !empty($deviceInfo['device_ids'] ?? [])) {
        //     $kochava = new KochavaService($link);
        //     $kochava->sendEvent('begin_checkout', [
        //         'user_id' => $deposit->user->uid,
        //         'content_id' => $deposit->order_no,
        //         'currency' => $deposit->currency,
        //         'name' => 'Deposit',
        //     ], $deviceInfo);
        // }

        // Facebook
        $facebook = new FacebookConversionsService($link);
        if ($facebook->isEnabled()) {
            $userData = FacebookConversionsService::userDataFromDeposit($deposit, $deviceInfo);
            $userData['event_time'] = $deviceInfo['usertime'] ?? time();
            $facebook->sendEvent(
                'InitiateCheckout',
                $userData,
                ['currency' => strtolower($deposit->currency), 'value' => (float) $deposit->amount],
                'begin_checkout_' . $deposit->order_no
            );
        }

        $cioTs = time();
        app(CustomerIOService::class)->sendEvent(
            $deposit->user,
            'initiate_purchase'
        );
    }
}
