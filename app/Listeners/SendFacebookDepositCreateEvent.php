<?php

namespace App\Listeners;

use App\Events\DepositCreated;
use App\Services\AgentService;
use App\Services\FacebookConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendFacebookDepositCreateEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(DepositCreated $event): void
    {
        $deposit = $event->deposit->loadMissing('user');
        $agent = AgentService::getAgentForUser($deposit->user);
        $deviceInfo = $event->deviceInfo;
        $userData = FacebookConversionsService::userDataFromDeposit($deposit, $deviceInfo);
        $userData['event_time'] = $deviceInfo['usertime'] ?? time();

        $service = new FacebookConversionsService($agent);
        $service->sendEvent(
            'begin_checkout',
            $userData,
            [
                'currency' => strtolower($deposit->currency),
                'value' => (float) $deposit->amount,
            ],
            'begin_checkout_' . $deposit->order_no
        );
    }
}
