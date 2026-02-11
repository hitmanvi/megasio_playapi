<?php

namespace App\Providers;

use App\Events\DepositCompleted;
use App\Events\OrderCompleted;
use App\Events\VipLevelUpgraded;
use App\Listeners\AddVipExpOnOrderCompleted;
use App\Listeners\CreateDepositBonusTask;
use App\Listeners\CreateInvitationDepositReward;
use App\Listeners\CreateInvitationVipReward;
use App\Listeners\NotifyVipLevelUpgraded;
use App\Listeners\CreateRollover;
use App\Listeners\RecordUserRecentGame;
use App\Listeners\UpdateRolloverProgress;
use App\Listeners\UpdateUserWager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureEventListeners();
    }

    /**
     * Configure event listeners.
     */
    protected function configureEventListeners(): void
    {
        Event::listen(OrderCompleted::class, RecordUserRecentGame::class);
        Event::listen(OrderCompleted::class, UpdateUserWager::class);
        Event::listen(OrderCompleted::class, UpdateRolloverProgress::class);
        Event::listen(OrderCompleted::class, AddVipExpOnOrderCompleted::class);
        Event::listen(DepositCompleted::class, CreateInvitationDepositReward::class);
        Event::listen(DepositCompleted::class, CreateDepositBonusTask::class);
        // Event::listen(DepositCompleted::class, CreateRollover::class);
        Event::listen(VipLevelUpgraded::class, CreateInvitationVipReward::class);
        Event::listen(VipLevelUpgraded::class, NotifyVipLevelUpgraded::class);
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // 游戏提供商回调接口的频率限制
        RateLimiter::for('gp', function (Request $request) {
            $maxAttempts = config('providers.rate_limit.max_attempts', 1000);
            $decayMinutes = config('providers.rate_limit.decay_minutes', 1);

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->ip());
        });
    }
}
