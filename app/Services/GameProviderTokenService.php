<?php

namespace App\Services;

use App\Models\GameProviderToken;
use App\Models\User;
use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class GameProviderTokenService
{
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
            return $existingToken->token;
        }

        // 生成新的 token
        $token = $this->generateToken();
        $expiresAt = $expiresInMinutes ? now()->addMinutes($expiresInMinutes) : null;

        // 创建 token 记录
        GameProviderToken::create([
            'user_id' => $userId,
            'provider' => $provider,
            'currency' => $currency,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * 验证 Token 是否有效
     *
     * @param string $token Token值
     * @return GameProviderToken|null 如果有效返回 token 记录，否则返回 null
     */
    public function verify(string $token): ?GameProviderToken
    {
        $tokenRecord = GameProviderToken::where('token', $token)
            ->valid()
            ->first();

        if (!$tokenRecord) {
            return null;
        }

        // 检查是否过期
        if ($tokenRecord->isExpired()) {
            return null;
        }

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

        return $query->delete();
    }

    /**
     * 清理过期的 Token
     *
     * @return int 删除的记录数
     */
    public function cleanExpired(): int
    {
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
}

