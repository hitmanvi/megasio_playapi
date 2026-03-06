<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\AgentService;
use App\Services\FacebookConversionsService;
use App\Services\KochavaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRegistrationEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        $link = AgentService::getAgentLinkForUser($user);
        $deviceInfo = $event->deviceInfo;

        // Kochava
        if (!empty($deviceInfo['kochava_device_id']) || !empty($deviceInfo['device_ids'] ?? [])) {
            $kochava = new KochavaService($link);
            $kochava->sendEvent('register', [
                'uid' => $user->uid,
                'event_id' => 'register_' . $user->uid,
            ], $deviceInfo);
        }

        // Facebook
        $facebook = new FacebookConversionsService($link);
        if ($facebook->isEnabled()) {
            $userData = FacebookConversionsService::userDataFromUser($user, $deviceInfo);
            $userData['event_time'] = $deviceInfo['usertime'] ?? time();
            $facebook->sendEvent('CompleteRegistration', $userData, ['status' => 'registered'], 'register_' . $user->uid);
        }
    }
}
