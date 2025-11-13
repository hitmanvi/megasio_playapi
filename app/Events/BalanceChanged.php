<?php

namespace App\Events;

use App\Models\Balance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BalanceChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 用户 ID
     */
    public int $userId;

    /**
     * 余额
     */
    public Balance $balance;

    /**
     * 变动金额
     */
    public float $amount;

    /**
     * 操作类型 (add/subtract)
     */
    public string $operation;

    /**
     * 余额类型 (available/frozen)
     */
    public string $type;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, Balance $balance, float $amount, string $operation, string $type)
    {
        $this->userId = $userId;
        $this->balance = $balance;
        $this->amount = $amount;
        $this->operation = $operation;
        $this->type = $type;
    }
}
