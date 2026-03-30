<?php

namespace App\Listeners;

use App\Events\UserMineVisited;
use App\Services\CustomerIOService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendMineVisitEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(UserMineVisited $event): void
    {
        app(CustomerIOService::class)->sendEvent($event->user, 'visit', time(), [
            'last_visit_at' => $event->user->last_visit_at?->unix(),
            'email' => $event->user->email,
        ]);
    }
}
