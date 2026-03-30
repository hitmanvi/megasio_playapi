<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\AgentService;
use App\Services\CustomerIOService;
use App\Services\FacebookConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRegistrationEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        $link = AgentService::getAgentLinkForUser($user);
        $deviceInfo = $event->deviceInfo;

        // Kochava
        // if (!empty($deviceInfo['kochava_device_id']) || !empty($deviceInfo['device_ids'] ?? [])) {
        //     $kochava = new KochavaService($link);
        //     $kochava->sendEvent('register', [
        //         'user_id' => $user->uid,
        //         'user_name' => $user->uid,
        //     ], $deviceInfo);
        // }

        // Facebook
        $facebook = new FacebookConversionsService($link);
        if ($facebook->isEnabled()) {
            $userData = FacebookConversionsService::userDataFromUser($user, $deviceInfo);
            $userData['event_time'] = $deviceInfo['usertime'] ?? time();
            $facebook->sendEvent('CompleteRegistration', $userData, ['status' => 'registered'], 'register_' . $user->uid);
        }

        // Customer.io：创建/更新 profile 并发送 sign_up（见 CustomerIOService::createCustomer）
        app(CustomerIOService::class)->createCustomer($user);
    }
}
