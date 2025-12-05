<?php

use App\Enums\ErrorCode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Exceptions\Exception;
use Illuminate\Support\Facades\Log;
use App\Http\Middleware\VerifyProviderIpWhitelist;
use App\Http\Middleware\LogRequestResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 注册游戏提供商 IP 白名单验证中间件别名
        $middleware->alias([
            'provider.ip' => VerifyProviderIpWhitelist::class,
            'log.request' => LogRequestResponse::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // 每天运行一次 Funky 游戏同步
        $schedule->command('import:funky_games')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        
        $exceptions->render(function (\Exception $e) {
            Log::error($e->getMessage());
            $resp = [
                'code'   => ErrorCode::INTERNAL_ERROR->value,
                'errmsg' => $e->getMessage(),
                'data'   => null,
            ];
            return response()->json($resp);
        });
    })->create();
