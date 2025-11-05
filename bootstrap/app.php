<?php

use App\Enums\ErrorCode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\Exception;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
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
