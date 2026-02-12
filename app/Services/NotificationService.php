<?php

namespace App\Services;

use App\Jobs\SendWebSocketMessage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * 创建用户通知
     *
     * @param int $userId 用户ID
     * @param string $category 分类
     * @param string $title 标题
     * @param string $content 内容
     * @param array|null $data 额外数据
     * @return Notification
     */
    public function createUserNotification(
        int $userId,
        string $category,
        string $title,
        string $content,
        ?array $data = null
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => Notification::TYPE_USER,
            'category' => $category,
            'title' => $title,
            'content' => $content,
            'data' => $data,
        ]);

        // 推送 WebSocket 通知
        $this->sendNotificationWebSocket($notification);

        return $notification;
    }

    /**
     * 发送通知的 WebSocket 推送
     *
     * @param Notification $notification
     * @return void
     */
    protected function sendNotificationWebSocket(Notification $notification): void
    {
        try {
            // 只推送用户通知（不推送系统通知）
            if ($notification->type !== Notification::TYPE_USER || !$notification->user_id) {
                return;
            }

            // 加载用户关联以获取 uid
            if (!$notification->relationLoaded('user')) {
                $notification->load('user');
            }

            if (!$notification->user || !$notification->user->uid) {
                return;
            }

            // 准备 WebSocket 消息数据
            $data = [
                'id' => $notification->id,
                'type' => $notification->type,
                'category' => $notification->category,
                'title' => $notification->title,
                'content' => $notification->content,
                'data' => $notification->data,
                'read_at' => $notification->read_at ? $notification->read_at->toIso8601String() : null,
                'created_at' => $notification->created_at->toIso8601String(),
            ];

            // 分发 WebSocket 推送任务
            SendWebSocketMessage::dispatch(
                $notification->user->uid,
                'notification',
                $data
            );
        } catch (\Exception $e) {
            // 记录错误但不影响主流程
            Log::warning('Failed to send notification via WebSocket', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 创建欢迎通知
     *
     * @param int $userId 用户ID
     * @return Notification
     */
    public function createWelcomeNotification(int $userId): Notification
    {
        return $this->createUserNotification(
            $userId,
            Notification::CATEGORY_REGISTER,
            'welcome',
            'Contact your exclusive customer service.'
        );
    }

    /**
     * 创建充值成功通知
     *
     * @param int $userId 用户ID
     * @param float $amount 充值金额
     * @param string $currency 货币类型
     * @param string $orderNo 订单号
     * @return Notification
     */
    public function createDepositSuccessNotification(int $userId, float $amount, string $currency, string $orderNo): Notification
    {
        // 格式化金额显示
        $formattedAmount = number_format($amount, 2, '.', '');
        $content = "\${$formattedAmount} credited to your game balance.";

        return $this->createUserNotification(
            $userId,
            Notification::CATEGORY_DEPOSIT_SUCCESS,
            'deposit',
            $content,
            [
                'amount' => $amount,
                'currency' => $currency,
                'order_no' => $orderNo,
            ]
        );
    }

    /**
     * 创建提现成功通知
     *
     * @param int $userId 用户ID
     * @param float $amount 提现金额
     * @param string $currency 货币类型
     * @param string $orderNo 订单号
     * @return Notification
     */
    public function createWithdrawSuccessNotification(int $userId, float $amount, string $currency, string $orderNo): Notification
    {
        // 格式化金额显示
        $formattedAmount = number_format($amount, 2, '.', '');
        $content = "\${$formattedAmount} has been processed successfully.";

        return $this->createUserNotification(
            $userId,
            Notification::CATEGORY_WITHDRAW_SUCCESS,
            'withdrawal',
            $content,
            [
                'amount' => $amount,
                'currency' => $currency,
                'order_no' => $orderNo,
            ]
        );
    }

    /**
     * 创建 Bonus Task 通知
     *
     * @param int $userId 用户ID
     * @param float $amount 奖励金额
     * @param string $currency 货币类型
     * @param string $taskNo 任务编号
     * @param string|null $bonusName 奖励名称（可选，用于判断类型）
     * @return Notification
     */
    public function createBonusTaskNotification(int $userId, float $amount, string $currency, string $taskNo, ?string $bonusName = null): Notification
    {
        // 格式化金额显示
        $formattedAmount = number_format($amount, 2, '.', '');
        
        // 根据 task_no 或 bonus_name 判断类型，生成 "Via ..." 文本
        $viaText = 'Via Deposit';
        $viaValue = 'Deposit';
        if ($taskNo === 'FIRST_DEPOSIT_BONUS' || ($bonusName && stripos($bonusName, 'First Deposit') !== false)) {
            $viaText = 'Via First Deposit';
            $viaValue = 'First Deposit';
        } elseif ($taskNo === 'SECOND_DEPOSIT_BONUS' || ($bonusName && stripos($bonusName, 'Second Deposit') !== false)) {
            $viaText = 'Via Second Deposit';
            $viaValue = 'Second Deposit';
        } elseif ($taskNo === 'THIRD_DEPOSIT_BONUS' || ($bonusName && stripos($bonusName, 'Third Deposit') !== false)) {
            $viaText = 'Via Third Deposit';
            $viaValue = 'Third Deposit';
        } elseif (strpos($taskNo, 'DAILY_DEPOSIT_BONUS') === 0 || ($bonusName && stripos($bonusName, 'Daily Deposit') !== false)) {
            $viaText = 'Via Daily Deposit';
            $viaValue = 'Daily Deposit';
        }
        
        $content = "\${$formattedAmount} credited to your bonus balance. {$viaText}.";

        return $this->createUserNotification(
            $userId,
            Notification::CATEGORY_BONUS_TASK,
            'bonus',
            $content,
            [
                'amount' => $amount,
                'currency' => $currency,
                'task_no' => $taskNo,
                'via' => $viaValue,
            ]
        );
    }

    /**
     * 创建 VIP 等级提升通知
     *
     * @param int $userId 用户ID
     * @param int $newLevel 新等级（如 4）
     * @return Notification
     */
    public function createVipLevelUpNotification(int $userId, int $newLevel): Notification
    {
        $levelNumber = (string) $newLevel;
        
        $title = "vip level up to {$levelNumber}";
        $content = "Congratulations! You've been upgraded to VIP Level {$levelNumber}!";

        return $this->createUserNotification(
            $userId,
            Notification::CATEGORY_VIP_LEVEL_UP,
            $title,
            $content,
            [
                'level' => $newLevel,
                'level_number' => $levelNumber,
            ]
        );
    }

    /**
     * 创建 Bonus Task 完成通知
     *
     * @param int $userId 用户ID
     * @param float $amount 奖励金额
     * @param string $currency 货币类型
     * @param string $taskNo 任务编号
     * @param string|null $bonusName 奖励名称（可选，用于判断类型）
     * @return Notification
     */
    public function createBonusTaskCompletedNotification(int $userId, float $amount, string $currency, string $taskNo, ?string $bonusName = null): Notification
    {
        // 格式化金额显示
        $formattedAmount = number_format($amount, 2, '.', '');
        
        // 根据 task_no 或 bonus_name 判断类型，生成 "via the ... Bonus you claimed!" 文本
        $viaText = 'the Deposit Bonus';
        if ($taskNo === 'FIRST_DEPOSIT_BONUS' || ($bonusName && stripos($bonusName, 'First Deposit') !== false)) {
            $viaText = 'the First Deposit Bonus';
        } elseif ($taskNo === 'SECOND_DEPOSIT_BONUS' || ($bonusName && stripos($bonusName, 'Second Deposit') !== false)) {
            $viaText = 'the Second Deposit Bonus';
        } elseif ($taskNo === 'THIRD_DEPOSIT_BONUS' || ($bonusName && stripos($bonusName, 'Third Deposit') !== false)) {
            $viaText = 'the Third Deposit Bonus';
        } elseif (strpos($taskNo, 'DAILY_DEPOSIT_BONUS') === 0 || ($bonusName && stripos($bonusName, 'Daily Deposit') !== false)) {
            $viaText = 'the Daily Deposit Bonus';
        }
        
        $content = "Congratulations! \${$formattedAmount} has been credited to your game balance via {$viaText} you claimed!";

        return $this->createUserNotification(
            $userId,
            Notification::CATEGORY_BONUS_TASK_COMPLETED,
            'bonus',
            $content,
            [
                'amount' => $amount,
                'currency' => $currency,
                'task_no' => $taskNo,
                'via' => $viaText,
            ]
        );
    }
}
