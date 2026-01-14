<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\BonusTask;

class UpdateBonusTaskWager
{
    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        if (!$order->user_id || $order->amount <= 0) {
            return;
        }

        // 查找用户当前进行中的 bonus 任务（只能有一个）
        $bonusTask = BonusTask::where('user_id', $order->user_id)
            ->where('status', BonusTask::STATUS_ACTIVE)
            ->first();

        if (!$bonusTask) {
            return;
        }

        $bonusTask->addWager((float) $order->amount);
    }
}
