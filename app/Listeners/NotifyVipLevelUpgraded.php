<?php

namespace App\Listeners;

use App\Events\VipLevelUpgraded;
use App\Services\NotificationService;
use App\Services\VipService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyVipLevelUpgraded implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;
    protected VipService $vipService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
        $this->vipService = new VipService();
    }

    /**
     * Handle the event.
     */
    public function handle(VipLevelUpgraded $event): void
    {
        $user = $event->user;
        $newLevel = $event->newLevel;

        // 获取新等级的配置，提取奖励金额
        $levelInfo = $this->vipService->getLevelInfo($newLevel);
        $rewardAmount = 0;
        $currency = config('app.currency', 'USD');

        if ($levelInfo && isset($levelInfo['benefits']) && is_array($levelInfo['benefits'])) {
            $benefits = $levelInfo['benefits'];
            
            // 检查是否有 level_cash_bonus
            if (isset($benefits['level_cash_bonus']) && !empty($benefits['level_cash_bonus'])) {
                $levelCashBonus = $benefits['level_cash_bonus'];
                
                // level_cash_bonus 可以是数字（金额）或数组（包含 amount 和 currency）
                if (is_numeric($levelCashBonus)) {
                    $rewardAmount = (float) $levelCashBonus;
                } elseif (is_array($levelCashBonus)) {
                    $rewardAmount = isset($levelCashBonus['amount']) ? (float) $levelCashBonus['amount'] : 0;
                    $currency = isset($levelCashBonus['currency']) ? $levelCashBonus['currency'] : config('app.currency', 'USD');
                }
            }
        }

        // 创建 VIP 等级提升通知（包含奖励金额）
        $this->notificationService->createVipLevelUpNotification(
            $user->id,
            $newLevel,
            $rewardAmount,
            $currency
        );
    }
}
