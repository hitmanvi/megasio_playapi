<?php

namespace App\Providers;

use App\Events\DepositCompleted;
use App\Events\OrderCompleted;
use App\Events\VipLevelUpgraded;
use App\Listeners\AddVipExpOnOrderCompleted;
use App\Listeners\CheckBonusTaskDeplete;
use App\Listeners\CreateDepositBonusTask;
use App\Listeners\CreateInvitationDepositReward;
use App\Listeners\CreateInvitationVipReward;
use App\Listeners\CreateRollover;
use App\Listeners\NotifyVipLevelUpgraded;
use App\Listeners\RecordUserRecentGame;
use App\Listeners\UpdateRolloverProgress;
use App\Listeners\UpdateUserWager;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 事件监听器映射
     * 只注册需要启用的监听器，未列出的监听器不会被注册
     */
    protected $listen = [
        OrderCompleted::class => [
            RecordUserRecentGame::class,
            UpdateUserWager::class,
            CheckBonusTaskDeplete::class,
            AddVipExpOnOrderCompleted::class,
            // UpdateRolloverProgress::class,
            // CreateRollover::class, // 已禁用，但代码保留
        ],
        DepositCompleted::class => [
            CreateInvitationDepositReward::class,
            CreateDepositBonusTask::class,
            // CreateRollover::class, // 已禁用，但代码保留
        ],
        VipLevelUpgraded::class => [
            CreateInvitationVipReward::class,
            NotifyVipLevelUpgraded::class,
        ],
    ];

    /**
     * 禁用自动发现，使用手动注册
     * 这样可以精确控制哪些监听器启用，哪些禁用
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
