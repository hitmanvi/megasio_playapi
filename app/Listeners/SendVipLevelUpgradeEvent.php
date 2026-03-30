<?php

namespace App\Listeners;

use App\Events\VipLevelUpgraded;
use App\Services\CustomerIOService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendVipLevelUpgradeEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function handle(VipLevelUpgraded $event): void
    {
        $cio = app(CustomerIOService::class);
        $user = $event->user;

        $cio->update($user, ['vip' => $event->newLevel]);

        $cio->sendEvent($user, 'vip', time(), [
            'vip_level' => $event->newLevel,
        ]);
    }
}
