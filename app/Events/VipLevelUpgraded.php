<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VipLevelUpgraded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 用户
     */
    public User $user;

    /**
     * 旧等级
     */
    public string $oldLevel;

    /**
     * 新等级
     */
    public string $newLevel;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, string $oldLevel, string $newLevel)
    {
        $this->user = $user;
        $this->oldLevel = $oldLevel;
        $this->newLevel = $newLevel;
    }
}
