<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\KochavaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendKochavaRegistrationEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(UserRegistered $event): void
    {
        $service = new KochavaService();
        $deviceInfo = $event->deviceInfo;
        if (empty($deviceInfo['kochava_device_id']) && empty($deviceInfo['device_ids'] ?? [])) {
            return;
        }
        $service->sendEvent('Registration', [
            'user_id' => $event->user->id,
            'uid' => $event->user->uid,
        ], $deviceInfo);
    }
}
