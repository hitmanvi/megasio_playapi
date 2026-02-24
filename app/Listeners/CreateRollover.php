<?php

namespace App\Listeners;

use App\Events\DepositCompleted;
use App\Models\Rollover;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateRollover implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(DepositCompleted $event): void
    {
        $deposit = $event->deposit;

        // 创建 rollover，required_wager 是充值金额的 1 倍
        // 初始状态为 pending，等待按顺序激活
        $rollover = Rollover::create([
            'user_id' => $deposit->user_id,
            'source_type' => Rollover::SOURCE_TYPE_DEPOSIT,
            'related_id' => $deposit->id,
            'currency' => $deposit->currency,
            'amount' => $deposit->amount,
            'required_wager' => $deposit->amount, // 1倍
            'current_wager' => 0,
            'status' => Rollover::STATUS_PENDING,
        ]);

        // 如果没有其他激活的 rollover，则激活这个新创建的
        $hasActiveRollover = Rollover::where('user_id', $deposit->user_id)
            ->where('currency', $deposit->currency)
            ->where('status', Rollover::STATUS_ACTIVE)
            ->exists();

        if (!$hasActiveRollover) {
            $rollover->status = Rollover::STATUS_ACTIVE;
            $rollover->save();
        }
    }
}
