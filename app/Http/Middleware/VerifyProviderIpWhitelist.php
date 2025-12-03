<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyProviderIpWhitelist
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $provider  提供商名称
     */
    public function handle(Request $request, Closure $next, string $provider): Response
    {
        // 获取该提供商的 IP 白名单配置
        $whitelist = config("providers.{$provider}.ip_whitelist", []);
        
        // 如果未配置白名单，则允许所有 IP（开发环境）
        if (empty($whitelist)) {
            return $next($request);
        }

        // 获取客户端 IP
        $clientIp = $this->getClientIp($request);

        // 检查 IP 是否在白名单中
        if (!$this->isIpAllowed($clientIp, $whitelist)) {
            throw new Exception(ErrorCode::FORBIDDEN, 'IP address not allowed');
        }

        return $next($request);
    }

    /**
     * 获取客户端真实 IP
     */
    protected function getClientIp(Request $request): string
    {
        // 优先从 X-Forwarded-For 获取（如果使用代理）
        $ip = $request->header('X-Forwarded-For');
        if ($ip) {
            // X-Forwarded-For 可能包含多个 IP，取第一个
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }

        // 如果没有，尝试 X-Real-IP
        if (!$ip) {
            $ip = $request->header('X-Real-IP');
        }

        // 最后使用 Laravel 的 ip() 方法
        return $ip ?: $request->ip();
    }

    /**
     * 检查 IP 是否在白名单中
     * 支持单个 IP 和 CIDR 格式
     */
    protected function isIpAllowed(string $ip, array $whitelist): bool
    {
        foreach ($whitelist as $allowedIp) {
            // 如果是 CIDR 格式（例如：192.168.1.0/24）
            if (strpos($allowedIp, '/') !== false) {
                if ($this->ipInCidr($ip, $allowedIp)) {
                    return true;
                }
            } else {
                // 直接匹配 IP
                if ($ip === $allowedIp) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 检查 IP 是否在 CIDR 范围内
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - (int)$mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}

