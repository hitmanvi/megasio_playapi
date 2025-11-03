<?php

namespace App\GameProviders;

use App\Contracts\GameProviderInterface;

/**
 * Netflix 游戏提供商实现
 */
class NetflixProvider implements GameProviderInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 获取游戏demo地址
     */
    public function demo(string $gameId, array $params = []): ?string
    {
        $baseUrl = $this->config['demo_url'] ?? 'https://demo.netflix.com';
        $language = $params['language'] ?? 'en';
        
        return "{$baseUrl}/game/{$gameId}?lang={$language}";
    }

    /**
     * 创建游戏会话
     */
    public function session(string $gameId, array $params = []): array
    {
        // 验证必需参数
        if (empty($params['user_id'])) {
            throw new \InvalidArgumentException('user_id is required');
        }

        $apiUrl = $this->config['api_url'] ?? 'https://api.netflix.com';
        $apiKey = $this->config['api_key'] ?? '';
        $secret = $this->config['secret'] ?? '';

        // 构建请求数据
        $requestData = [
            'game_id' => $gameId,
            'user_id' => $params['user_id'],
            'currency' => $params['currency'] ?? 'USD',
            'language' => $params['language'] ?? 'en',
            'timestamp' => time(),
        ];

        // 这里应该调用实际的API
        // $response = Http::post("{$apiUrl}/session", $requestData);
        
        // 示例返回（实际应从API获取）
        return [
            'session_id' => 'nf_' . uniqid(),
            'game_url' => "{$apiUrl}/game/{$gameId}",
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addHours(1)->toIso8601String(),
        ];
    }
}
