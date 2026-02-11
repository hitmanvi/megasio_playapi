<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\BonusTask;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CheckBonusTaskDeplete implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        // 检查订单是否绑定了 bonus task
        if (!$order->bonus_task_id) {
            return;
        }

        // 加载 bonus task
        $bonusTask = BonusTask::find($order->bonus_task_id);
        
        if (!$bonusTask) {
            return;
        }

        // 刷新 bonus task 以获取最新状态（确保 last_bonus 是最新的）
        $bonusTask->refresh();

        // 检查是否满足 deplete 条件
        // 1. last_bonus < 0.1
        // 2. 任务状态为 pending 或 active
        // 3. 该 bonus task 绑定的所有订单都已完成（没有 pending 状态的订单）
        if ($bonusTask->last_bonus < 0.1 && ($bonusTask->isPending() || $bonusTask->isActive())) {
            // 检查该 bonus task 绑定的订单是否都已完成
            $hasUncompletedOrders = Order::where('bonus_task_id', $bonusTask->id)
                ->where('status', Order::STATUS_PENDING)
                ->exists();
            
            // 只有当没有未完成的订单时，才设置为 depleted
            if (!$hasUncompletedOrders) {
                $bonusTask->status = BonusTask::STATUS_DEPLETED;
                $bonusTask->save();
            }
        }
    }
}
