<?php

namespace App\Events;

use App\Models\Deposit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DepositCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Deposit $deposit;

    /** @var array Device info for Kochava (from extra_info or request) */
    public array $deviceInfo;

    public function __construct(Deposit $deposit, array $deviceInfo = [])
    {
        $this->deposit = $deposit;
        $this->deviceInfo = $deviceInfo;
    }
}
