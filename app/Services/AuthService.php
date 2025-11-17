<?php

namespace App\Services;

use App\Models\User;
use App\Enums\ErrorCode;
use App\Events\UserLoggedIn;
use App\Exceptions\Exception;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * 注册新用户
     *
     * @param array $data 用户数据
     * @return array 包含用户和 token 的数组
     * @throws Exception
     */
    public function register(array $data): array
    {
        // 至少需要提供手机号或邮箱其中一个
        if (empty($data['phone']) && empty($data['email'])) {
            throw new Exception(ErrorCode::PHONE_EMAIL_REQUIRED, [
                'phone' => ['Phone number and email cannot both be empty'],
                'email' => ['Phone number and email cannot both be empty'],
            ]);
        }

        $name = $data['name'] ?? ($data['phone'] ?? $data['email']);

        // 处理 area_code，移除 + 号
        $areaCode = $data['area_code'] ?? null;
        if ($areaCode && str_starts_with($areaCode, '+')) {
            $areaCode = substr($areaCode, 1);
        }

        // 创建用户
        $user = User::create([
            'uid' => User::generateUid(),
            'name' => $name,
            'phone' => $data['phone'] ?? null,
            'area_code' => $areaCode,
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => 'active',
            // invite_code 会在 boot 方法中自动生成
        ]);

        // 生成 token
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * 用户登录
     *
     * @param string $login 登录凭证（手机号或邮箱）
     * @param string $password 密码
     * @param string|null $ipAddress IP 地址
     * @param string|null $userAgent User Agent
     * @return array 包含 token 的数组
     * @throws Exception
     */
    public function login(string $login, string $password, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        // 判断是手机号还是邮箱
        $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        // 检查用户是否存在
        $user = User::where($loginField, $login)->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            throw new Exception(ErrorCode::INVALID_CREDENTIALS);
        }

        // 检查用户状态
        if ($user->status !== 'active') {
            throw new Exception(ErrorCode::ACCOUNT_DISABLED);
        }

        // 生成 token
        $token = $user->createToken('auth_token')->plainTextToken;

        // 触发登录事件，异步记录活动
        event(new UserLoggedIn($user, $ipAddress, $userAgent));

        return [
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * 用户登出
     *
     * @param User $user
     * @return void
     */
    public function logout(User $user): void
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }
    }

    /**
     * 刷新用户令牌
     *
     * @param User $user
     * @return array 包含新 token 的数组
     */
    public function refreshToken(User $user): array
    {
        // 删除当前令牌
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $currentToken */
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }
        
        // 创建新令牌
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * 修改密码
     *
     * @param User $user
     * @param string $currentPassword 当前密码
     * @param string $newPassword 新密码
     * @return void
     * @throws Exception
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new Exception(ErrorCode::CURRENT_PASSWORD_INCORRECT);
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }

    /**
     * 生成 JWT token（用于 WebSocket 认证）
     *
     * @param User $user
     * @return array 包含 token 和过期时间的数组
     */
    public function generateJwtToken(User $user): array
    {
        // JWT 密钥，使用 APP_KEY 或专门的 JWT_SECRET
        $secret = config('app.jwt_secret');
        
        // JWT payload
        $payload = [
            'iss' => config('app.url'), // Issuer
            'uid' => $user->uid, // User UID
            'iat' => time(), // Issued at
            'exp' => time() + (60 * 60 * 24), // Expiration time (24 hours)
        ];

        // 生成 JWT token
        $token = JWT::encode($payload, $secret, 'HS256');

        return [
            'token' => $token,
            'expires_in' => 60 * 60 * 24, // 24 hours in seconds
        ];
    }
}

