<?php

namespace App\Services;

use App\Models\GameProviderToken;
use App\Models\User;
use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class GameProviderTokenService
{
    /**
     * 缓存前缀
     */
    protected const CACHE_PREFIX = 'gp_token:';

    /**
     * 默认缓存时间（秒）- 10分钟
     */
    protected const CACHE_TTL = 600;

    /**
     * 生成游戏提供商用户 Token
     *
     * @param string $provider 游戏提供商标识
     * @param int $userId 用户ID
     * @param string $currency 货币类型
     * @param int|null $expiresInMinutes 过期时间（分钟），null 表示永不过期
     * @return string Token值
     * @throws Exception
     */
    public function issue(string $provider, int $userId, string $currency, ?int $expiresInMinutes = null): string
    {
        // 验证用户是否存在
        $user = User::find($userId);
        if (!$user) {
            throw new Exception(ErrorCode::USER_NOT_FOUND, 'User not found');
        }

        // 检查是否已存在有效的 token
        $existingToken = GameProviderToken::where('user_id', $userId)
            ->where('provider', $provider)
            ->where('currency', $currency)
            ->valid()
            ->first();

        if ($existingToken) {
            // 写入缓存
            $this->cacheToken($existingToken);
            return $existingToken->token;
        }

        // 生成新的 token
        $token = $this->generateToken();
        $expiresAt = $expiresInMinutes ? now()->addMinutes($expiresInMinutes) : null;

        // 创建 token 记录
        $tokenRecord = GameProviderToken::create([
            'user_id' => $userId,
            'provider' => $provider,
            'currency' => $currency,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        // 写入缓存
        $this->cacheToken($tokenRecord);

        return $token;
    }

    /**
     * 验证 Token 是否有效（带缓存）
     *
     * @param string $token Token值
     * @return GameProviderToken|null 如果有效返回 token 记录，否则返回 null
     */
    public function verify(string $token): ?GameProviderToken
    {
        $cacheKey = $this->getCacheKey($token);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            // 缓存命中，检查是否过期
            if ($cached === false) {
                // 缓存了无效标记
                return null;
            }

            $tokenRecord = new GameProviderToken($cached);
            $tokenRecord->exists = true;
            $tokenRecord->id = $cached['id'];

            if ($tokenRecord->isExpired()) {
                $this->forgetToken($token);
                return null;
            }

            return $tokenRecord;
        }

        // 缓存未命中，查询数据库
        $tokenRecord = GameProviderToken::where('token', $token)
            ->valid()
            ->first();

        if (!$tokenRecord) {
            // 缓存无效标记，防止缓存穿透
            Cache::put($cacheKey, false, 60);
            return null;
        }

        // 检查是否过期
        if ($tokenRecord->isExpired()) {
            Cache::put($cacheKey, false, 60);
            return null;
        }

        // 写入缓存
        $this->cacheToken($tokenRecord);

        return $tokenRecord;
    }

    /**
     * 根据用户ID、提供商和货币获取 Token
     *
     * @param int $userId 用户ID
     * @param string $provider 游戏提供商标识
     * @param string $currency 货币类型
     * @return string|null Token值，如果不存在或已过期返回 null
     */
    public function getToken(int $userId, string $provider, string $currency): ?string
    {
        $tokenRecord = GameProviderToken::where('user_id', $userId)
            ->where('provider', $provider)
            ->where('currency', $currency)
            ->valid()
            ->first();

        if (!$tokenRecord || $tokenRecord->isExpired()) {
            return null;
        }

        return $tokenRecord->token;
    }

    /**
     * 撤销 Token
     *
     * @param string $token Token值
     * @return bool 是否成功撤销
     */
    public function revoke(string $token): bool
    {
        $tokenRecord = GameProviderToken::where('token', $token)->first();

        if (!$tokenRecord) {
            return false;
        }

        // 清除缓存
        $this->forgetToken($token);

        return $tokenRecord->delete();
    }

    /**
     * 撤销用户的所有 Token（可选：指定提供商和货币）
     *
     * @param int $userId 用户ID
     * @param string|null $provider 游戏提供商标识（可选）
     * @param string|null $currency 货币类型（可选）
     * @return int 删除的记录数
     */
    public function revokeAll(int $userId, ?string $provider = null, ?string $currency = null): int
    {
        $query = GameProviderToken::where('user_id', $userId);

        if ($provider) {
            $query->where('provider', $provider);
        }

        if ($currency) {
            $query->where('currency', $currency);
        }

        // 先获取要删除的 token 以清除缓存
        $tokens = (clone $query)->pluck('token');
        foreach ($tokens as $token) {
            $this->forgetToken($token);
        }

        return $query->delete();
    }

    /**
     * 清理过期的 Token
     *
     * @return int 删除的记录数
     */
    public function cleanExpired(): int
    {
        // 获取过期的 token 以清除缓存
        $tokens = GameProviderToken::where('expires_at', '<=', now())->pluck('token');
        foreach ($tokens as $token) {
            $this->forgetToken($token);
        }

        return GameProviderToken::where('expires_at', '<=', now())->delete();
    }

    /**
     * 生成唯一的 Token
     *
     * @return string
     */
    protected function generateToken(): string
    {
        do {
            // 生成一个随机 token（使用 ULID 格式，更安全且有序）
            $token = Str::ulid()->toString();
        } while (GameProviderToken::where('token', $token)->exists());

        return $token;
    }

    /**
     * 获取缓存 key
     */
    protected function getCacheKey(string $token): string
    {
        return self::CACHE_PREFIX . $token;
    }

    /**
     * 缓存 token 数据
     */
    protected function cacheToken(GameProviderToken $tokenRecord): void
    {
        $cacheKey = $this->getCacheKey($tokenRecord->token);

        // 计算 TTL：如果有过期时间，取过期时间和默认 TTL 的较小值
        $ttl = self::CACHE_TTL;
        if ($tokenRecord->expires_at) {
            $secondsUntilExpiry = now()->diffInSeconds($tokenRecord->expires_at, false);
            if ($secondsUntilExpiry > 0) {
                $ttl = min($ttl, $secondsUntilExpiry);
            }
        }

        Cache::put($cacheKey, $tokenRecord->toArray(), $ttl);
    }

    /**
     * 清除 token 缓存
     */
    protected function forgetToken(string $token): void
    {
        Cache::forget($this->getCacheKey($token));
    }
}

