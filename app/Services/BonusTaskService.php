<?php

namespace App\Services;

use App\Models\BonusTask;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BonusTaskService
{
    protected BalanceService $balanceService;
    protected TransactionService $transactionService;

    public function __construct()
    {
        $this->balanceService = new BalanceService();
        $this->transactionService = new TransactionService();
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
     * 获取用户当前激活的 bonus task（未过期的）
     *
     * @param int $userId
     * @return BonusTask|null
     */
    public function getActiveBonusTask(int $userId): ?BonusTask
    {
        return BonusTask::where('user_id', $userId)
            ->where('status', BonusTask::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('expired_at')
                      ->orWhere('expired_at', '>', now());
            })
            ->first();
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

        if (!$task->isClaimable()) {
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
     * 激活任务
     *
     * @param BonusTask $task
     * @return void
     */
    public function activate(BonusTask $task): void
    {
        if ($task->isPending()) {
            $task->status = BonusTask::STATUS_ACTIVE;
            $task->save();
        }
    }

    /**
     * 扣减 bonus 余额（下注）
     * 同时增加 wager（流水）
     *
     * @param BonusTask $task
     * @param float $amount
     * @return bool
     */
    public function deductBonus(BonusTask $task, float $amount): bool
    {
        // 检查是否可以操作（包括过期检查）
        if (!$task->canOperate()) {
            // 如果任务已过期且处于 pending 或 active 状态，更新状态为过期
            if ($task->isExpired() && ($task->isPending() || $task->isActive())) {
                $task->status = BonusTask::STATUS_EXPIRED;
                $task->save();
            }
            return false;
        }
        
        // 检查余额是否足够
        if ($task->last_bonus < $amount) {
            return false;
        }
        
        // 扣减 bonus 余额
        $task->last_bonus -= $amount;
        
        // 增加 wager（流水）
        $task->wager = min($task->wager + $amount, $task->need_wager);
        
        // 检查是否完成
        if ($task->wager >= $task->need_wager && $task->isActive()) {
            // 任务完成，发放奖励并更新状态
            $this->completeTask($task);
        } else {
            $task->save();
        }
        
        return true;
    }

    /**
     * 完成任务：发放 cap_bonus 奖励并更新状态为已领取
     *
     * @param BonusTask $task
     * @return void
     */
    protected function completeTask(BonusTask $task): void
    {
        DB::transaction(function () use ($task) {
            // 计算奖励金额：cap_bonus
            $rewardAmount = (float) $task->cap_bonus;
            
            // 发放奖励到用户余额
            if ($rewardAmount > 0) {
                $this->balanceService->bonusTaskReward(
                    $task->user_id,
                    $task->currency,
                    $rewardAmount,
                    $task->id,
                    $task->bonus_name
                );
            }
            
            // 清空 last_bonus
            $task->last_bonus = 0;
            
            // 更新任务状态为已领取（因为奖励已自动发放）
            $task->status = BonusTask::STATUS_CLAIMED;
            $task->save();
        });
    }

    /**
     * 增加 bonus 余额（赢钱）
     *
     * @param BonusTask $task
     * @param float $amount
     * @return void
     */
    public function addBonus(BonusTask $task, float $amount): void
    {
        if (!$task->canOperate()) {
            return;
        }
        
        $task->last_bonus = $task->last_bonus + $amount;
        $task->save();
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
            'expired_at' => $task->expired_at?->toIso8601String(),
            'progress_percent' => $task->getProgressPercent(),
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
        ];
    }
}
