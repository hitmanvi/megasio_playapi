<?php

namespace App\Contracts;

/**
 * 游戏提供商接口
 * 
 * 所有游戏提供商需要实现此接口，用于统一接入标准
 */
interface GameProviderInterface
{
    /**
     * 获取游戏demo地址
     * 
     * @param string $gameId 游戏ID（提供商端的游戏标识）
     * @param array $params 额外参数，可能包含用户信息、语言等
     * @return string|null demo地址，如果游戏不支持demo则返回null
     */
    public function demo(string $gameId, array $params = []): ?string;

    /**
     * 创建游戏会话
     * 
     * @param string $gameId 游戏ID（提供商端的游戏标识）
     * @param array $params 会话参数，通常包含：
     *                      - user_id: 用户ID
     *                      - language: 语言代码
     *                      - 其他提供商特定参数
     * @return array 会话信息，通常包含：
     *              - session_id: 会话ID
     *              - game_url: 游戏URL
     *              - token: 认证令牌（如果需要）
     *              - 其他提供商特定的返回值
     * @throws \Exception 当创建会话失败时抛出异常
     */
    public function session(string $userId, string $gameId, array $params = []): string;
}
