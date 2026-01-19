<?php

namespace App\Services;

use App\Models\BonusTask;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BonusTaskService
{
    protected BalanceService $balanceService;
    protected TransactionService $transactionService;

    public function __construct(BalanceService $balanceService, TransactionService $transactionService)
    {
        $this->balanceService = $balanceService;
        $this->transactionService = $transactionService;
    }

    /**
     * 获取用户可领取的 BonusTask 列表
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getClaimableTasks(int $userId)
    {
        return BonusTask::query()
            ->where('user_id', $userId)
            ->claimable()
            ->ordered()
            ->get();
    }

    /**
     * 领取 BonusTask 奖励
     *
     * @param int $userId
     * @param int $taskId
     * @return array
     * @throws \Exception
     */
    public function claim(int $userId, int $taskId): array
    {
        $task = BonusTask::where('user_id', $userId)
            ->where('id', $taskId)
            ->first();

        if (!$task) {
            throw new \Exception('Bonus task not found');
        }

        if (!$task->isCompleted()) {
            throw new \Exception('Bonus task is not claimable');
        }

        return DB::transaction(function () use ($task, $userId) {
            // 计算领取金额：min(cap_bonus, last_bonus)
            $claimAmount = $task->getClaimAmount();
            
            // 更新任务状态
            $task->status = BonusTask::STATUS_CLAIMED;
            $task->save();

            // 使用任务中存储的币种
            $currency = $task->currency;

            // 将奖励添加到用户余额（使用专门的 bonusTaskReward 方法）
            $result = $this->balanceService->bonusTaskReward(
                $userId,
                $currency,
                $claimAmount,
                $task->id,
                $task->bonus_name
            );

            return [
                'task' => $task,
                'balance' => $result['balance'],
                'transaction' => $result['transaction'],
                'claim_amount' => $claimAmount,
                'currency' => $currency,
            ];
        });
    }

    /**
     * 格式化 BonusTask 数据
     *
     * @param BonusTask $task
     * @return array
     */
    public function formatBonusTask(BonusTask $task): array
    {
        return [
            'id' => $task->id,
            'task_no' => $task->task_no,
            'bonus_name' => $task->bonus_name,
            'cap_bonus' => (float) $task->cap_bonus,
            'base_bonus' => (float) $task->base_bonus,
            'last_bonus' => (float) $task->last_bonus,
            'need_wager' => (float) $task->need_wager,
            'wager' => (float) $task->wager,
            'status' => $task->status,
            'currency' => $task->currency,
            'progress_percent' => $task->getProgressPercent(),
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
        ];
    }
}
