<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Services\CustomerIOService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendLoginEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function handle(UserLoggedIn $event): void
    {
        $cio = app(CustomerIOService::class);
        $cio->syncEmailOnLogin($event->user);
        $cio->sendEvent($event->user, 'sign_in');
    }
}
