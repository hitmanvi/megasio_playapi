<?php

namespace App\Listeners;

use App\Events\VipLevelUpgraded;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyVipLevelUpgraded implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    /**
     * Handle the event.
     */
    public function handle(VipLevelUpgraded $event): void
    {
        $user = $event->user;
        $newLevel = $event->newLevel;

        // 创建 VIP 等级提升通知（暂时不包含奖励金额）
        $this->notificationService->createVipLevelUpNotification(
            $user->id,
            $newLevel
        );
    }
}
