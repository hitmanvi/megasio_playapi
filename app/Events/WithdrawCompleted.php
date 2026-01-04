<?php

namespace App\Events;

use App\Models\Withdraw;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 提现订单
     */
    public Withdraw $withdraw;

    /**
     * Create a new event instance.
     */
    public function __construct(Withdraw $withdraw)
    {
        $this->withdraw = $withdraw;
    }
}

