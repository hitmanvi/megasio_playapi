<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\CustomerIOService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SyncRegisteredUserToCustomerIO implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function handle(UserRegistered $event): void
    {
        app(CustomerIOService::class)->createCustomer($event->user);
    }
}
