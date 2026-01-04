<?php

namespace App\Events;

use App\Models\Deposit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DepositCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 存款订单
     */
    public Deposit $deposit;

    /**
     * Create a new event instance.
     */
    public function __construct(Deposit $deposit)
    {
        $this->deposit = $deposit;
    }
}

