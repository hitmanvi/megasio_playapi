<?php

namespace App\Services;

use App\Models\BonusTask;
use App\Models\Order;
use App\Jobs\SendWebSocketMessage;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BonusTaskService
{
    protected BalanceService $balanceService;
    protected TransactionService $transactionService;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->balanceService = new BalanceService();
        $this->transactionService = new TransactionService();
        $this->notificationService = new NotificationService();
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
     * 创建 BonusTask
     *
     * @param array $data 任务数据
     * @return BonusTask
     */
    public function createTask(array $data): BonusTask
    {
        // 如果没有设置 expired_at，根据配置计算过期时间
        if (!isset($data['expired_at'])) {
            $expireDays = config('app.bonus_expire_days');
            if ($expireDays !== null) {
                $data['expired_at'] = Carbon::now()->addDays($expireDays);
            }
        }

        $task = BonusTask::create($data);

        // 创建 Bonus Task 通知
        $this->notificationService->createBonusTaskNotification(
            $task->user_id,
            (float) $task->cap_bonus,
            $task->currency,
            $task->task_no,
            $task->bonus_name
        );

        return $task;
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
     * 检查并激活下一个待激活的任务
     * 如果当前没有激活的任务，自动激活最快要过期的 pending 任务
     *
     * @param int $userId
     * @return BonusTask|null 返回被激活的任务，如果没有则返回 null
     */
    public function activateNextPendingTask(int $userId): ?BonusTask
    {
        // 检查是否有激活的任务
        $activeTask = $this->getActiveBonusTask($userId);
        if ($activeTask) {
            return null;
        }

        // 查找最快要过期的待激活任务
        // 优先激活有过期时间的任务（按过期时间升序），然后是没有过期时间的任务（按创建时间升序）
        $pendingTask = BonusTask::where('user_id', $userId)
            ->where('status', BonusTask::STATUS_PENDING)
            ->where(function ($query) {
                // 只激活未过期的任务
                $query->whereNull('expired_at')
                      ->orWhere('expired_at', '>', now());
            })
            ->orderByRaw('CASE WHEN expired_at IS NULL THEN 1 ELSE 0 END') // 有过期时间的优先
            ->orderBy('expired_at', 'asc') // 按过期时间升序（最快要过期的在前）
            ->orderBy('created_at', 'asc') // 如果都没有过期时间，按创建时间升序
            ->first();

        if ($pendingTask) {
            $this->activate($pendingTask);
            return $pendingTask;
        }

        return null;
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
        
        // 确保 last_bonus 不会小于 0
        if ($task->last_bonus < 0) {
            $task->last_bonus = 0;
        }
        
        // 增加 wager（流水）
        $task->wager = min($task->wager + $amount, $task->need_wager);
        
        // 检查是否完成
        if ($task->wager >= $task->need_wager && $task->isActive()) {
            // 任务完成，发放奖励并更新状态
            $this->completeTask($task);
            // 刷新任务对象以获取最新状态
            $task->refresh();
        } else {
            // 检查 last_bonus 是否用完且任务未完成
            if ($task->last_bonus < 0.1 && ($task->isPending() || $task->isActive())) {
                // 检查该 bonus task 绑定的订单是否都已完成
                $hasUncompletedOrders = Order::where('bonus_task_id', $task->id)
                    ->where('status', Order::STATUS_PENDING)
                    ->exists();
                
                // 只有当没有未完成的订单时，才设置为 depleted
                if (!$hasUncompletedOrders) {
                    $task->status = BonusTask::STATUS_DEPLETED;
                }
            }
            $task->save();
        }
        
        // 发送 WebSocket 推送
        $this->sendBonusTaskUpdate($task, 'deduct', $amount);
        
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

            // 任务完成后，激活下一个待激活的任务
            $this->activateNextPendingTask($task->user_id);
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
        
        // 发送 WebSocket 推送
        $this->sendBonusTaskUpdate($task, 'add', $amount);
    }

    /**
     * 获取用户的 BonusTask 列表
     *
     * @param int $userId
     * @param string|array|null $status 状态过滤（可选，支持单个值或数组）
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTasks(int $userId, $status = null)
    {
        $query = BonusTask::query()
            ->where('user_id', $userId);

        // 如果提供了 status 参数，进行过滤
        if ($status !== null) {
            if (is_array($status)) {
                // 如果是数组，使用 whereIn
                $query->whereIn('status', $status);
            } else {
                // 如果是单个值，使用 where
                $query->where('status', $status);
            }
        }

        return $query->ordered()->get();
    }

    /**
     * 获取用户的 BonusTask 列表（分页）
     *
     * @param int $userId
     * @param string|array|null $status 状态过滤（可选，支持单个值或数组）
     * @param int $perPage 每页数量
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTasksPaginated(int $userId, $status = null, int $perPage = 20)
    {
        $query = BonusTask::query()
            ->where('user_id', $userId);

        // 如果提供了 status 参数，进行过滤
        if ($status !== null) {
            if (is_array($status)) {
                // 如果是数组，使用 whereIn
                $query->whereIn('status', $status);
            } else {
                // 如果是单个值，使用 where
                $query->where('status', $status);
            }
        }

        return $query->ordered()->paginate($perPage);
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

    /**
     * 发送 BonusTask 更新 WebSocket 推送
     *
     * @param BonusTask $task
     * @param string $operation 操作类型 (add/deduct)
     * @param float $amount 变动金额
     * @return void
     */
    protected function sendBonusTaskUpdate(BonusTask $task, string $operation, float $amount): void
    {
        try {
            // 加载用户关联以获取 uid
            if (!$task->relationLoaded('user')) {
                $task->load('user');
            }

            if (!$task->user || !$task->user->uid) {
                return;
            }

            // 准备 WebSocket 消息数据
            $data = [
                'task_id' => $task->id,
                'task_no' => $task->task_no,
                'bonus_name' => $task->bonus_name,
                'last_bonus' => (string) $task->last_bonus,
                'status' => $task->status,
                'currency' => $task->currency,
                'progress_percent' => $task->getProgressPercent(),
                'operation' => $operation,
                'amount' => (string) $amount,
            ];

            // 分发 WebSocket 推送任务
            SendWebSocketMessage::dispatch(
                $task->user->uid,
                'bonus_task.updated',
                $data
            );
        } catch (\Exception $e) {
            // 记录错误但不影响主流程
            Log::warning('Failed to send bonus task update via WebSocket', [
                'task_id' => $task->id,
                'user_id' => $task->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取用户的 BonusTask 统计数据
     *
     * @param int $userId
     * @return array
     */
    public function getStats(int $userId): array
    {
        // 计算所有 task 的 cap_bonus 总和
        $totalCapBonus = (float) BonusTask::where('user_id', $userId)
            ->sum('cap_bonus');

        return [
            'total' => $totalCapBonus,
            'currency' => config('app.currency', 'USD'),
        ];
    }
}
