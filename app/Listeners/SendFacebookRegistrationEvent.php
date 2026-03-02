<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\FacebookConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendFacebookRegistrationEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        $deviceInfo = $event->deviceInfo;
        $userData = FacebookConversionsService::userDataFromUser($user, $deviceInfo);
        $userData['event_time'] = $deviceInfo['usertime'] ?? time();

        $service = new FacebookConversionsService();
        $service->sendEvent(
            'CompleteRegistration',
            $userData,
            ['status' => 'registered'],
            'reg_' . $user->id . '_' . $user->created_at->timestamp
        );
    }
}
