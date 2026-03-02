<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    /** @var array Device info for Kochava (kochava_device_id, device_ids, device_ua, origination_ip) */
    public array $deviceInfo;

    public function __construct(User $user, array $deviceInfo = [])
    {
        $this->user = $user;
        $this->deviceInfo = $deviceInfo;
    }
}
