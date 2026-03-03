<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\AgentService;
use App\Services\FacebookConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendFacebookRegistrationEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        $agent = AgentService::getAgentForUser($user);
        $deviceInfo = $event->deviceInfo;
        $userData = FacebookConversionsService::userDataFromUser($user, $deviceInfo);
        $userData['event_time'] = $deviceInfo['usertime'] ?? time();

        $service = new FacebookConversionsService($agent);
        $service->sendEvent(
            'register',
            $userData,
            ['status' => 'registered'],
            'register_' . $user->id
        );
    }
}
