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
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use App\Exceptions\Exception as AppException;

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
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'code'   => ErrorCode::UNAUTHORIZED->value,
                    'errmsg' => $e->getMessage(),
                    'data'   => null,
                ]);
            }
            if ($e instanceof ValidationException) {
                return response()->json([
                    'code'   => ErrorCode::VALIDATION_ERROR->value,
                    'errmsg' => $e->getMessage(),
                    'data'   => null,
                ]);
            }
            if ($e instanceof AppException) {
                return response()->json([
                    'code'   => $e->getErrorCode()->value,
                    'errmsg' => $e->getErrorCode()->getMessage(),
                    'data'   => null,
                ]);
            }
            $resp = [
                'code'   => ErrorCode::INTERNAL_ERROR->value,
                'errmsg' => "Server error",
                'data'   => null,
            ];
            return response()->json($resp);
        });
    })->create();
