<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 记录请求信息
        $this->logRequest($request);

        // 执行请求并获取响应
        $response = $next($request);

        // 记录响应信息
        $this->logResponse($request, $response);

        return $response;
    }

    /**
     * 记录请求信息
     */
    protected function logRequest(Request $request): void
    {
        $data = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'query' => $request->query->all(),
            'body' => $this->sanitizeData($request->all()),
        ];

        Log::info('Request', $data);
    }

    /**
     * 记录响应信息
     */
    protected function logResponse(Request $request, Response $response): void
    {
        $content = $response->getContent();
        $decodedContent = null;

        // 尝试解码 JSON 响应
        $contentType = $response->headers->get('Content-Type', '');
        if ($contentType === 'application/json' || 
            strpos($contentType, 'application/json') === 0) {
            $decodedContent = json_decode($content, true);
        }

        $data = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'response' => $decodedContent !== null ? $decodedContent : (strlen($content) > 1000 ? substr($content, 0, 1000) . '...' : $content),
        ];

        // 根据状态码选择日志级别
        if ($response->getStatusCode() >= 500) {
            Log::error('Response', $data);
        } elseif ($response->getStatusCode() >= 400) {
            Log::warning('Response', $data);
        } else {
            Log::info('Response', $data);
        }
    }

    /**
     * 清理敏感信息
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'secret', 'api_key', 'api_secret', 'authorization'];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }

        return $data;
    }

    /**
     * 清理请求头中的敏感信息
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key'];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $headers[$key] = ['***'];
            }
        }

        return $headers;
    }
}
