<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        // 注意：事件监听器通过 Laravel 11 的自动发现机制自动注册
        // 不需要手动注册，否则会导致监听器被执行两次
        // 自动发现机制会根据监听器 handle() 方法的类型提示自动匹配事件
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
