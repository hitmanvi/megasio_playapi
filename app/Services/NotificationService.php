<?php

namespace App\Services;

use App\Models\Notification;

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
        return Notification::create([
            'user_id' => $userId,
            'type' => Notification::TYPE_USER,
            'category' => $category,
            'title' => $title,
            'content' => $content,
            'data' => $data,
        ]);
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
            Notification::CATEGORY_SYSTEM_ANNOUNCEMENT,
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
}
