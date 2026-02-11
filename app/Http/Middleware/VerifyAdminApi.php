<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyAdminApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 验证 API Key
        $apiKey = $request->header('X-Admin-API-Key') ?? $request->input('api_key');
        $expectedApiKey = config('admin.api_key');

        if (empty($expectedApiKey)) {
            Log::error('Admin API key not configured');
            return $this->unauthorizedResponse('Admin API key not configured');
        }

        if (empty($apiKey) || $apiKey !== $expectedApiKey) {
            Log::warning('Invalid admin API key attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return $this->unauthorizedResponse('Invalid API key');
        }

        return $next($request);
    }

    /**
     * 返回未授权响应
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): Response
    {
        return response()->json([
            'code' => ErrorCode::UNAUTHORIZED->value,
            'errmsg' => $message,
            'data' => null,
        ], 401);
    }
}
