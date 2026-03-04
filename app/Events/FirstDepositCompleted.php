<?php

namespace App\Events;

use App\Models\Deposit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FirstDepositCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 首次充值成功的存款订单
     */
    public Deposit $deposit;

    public function __construct(Deposit $deposit)
    {
        $this->deposit = $deposit;
    }
}
