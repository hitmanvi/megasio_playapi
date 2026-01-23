<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invitation;
use App\Enums\ErrorCode;
use App\Events\UserLoggedIn;
use App\Exceptions\Exception;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Google\Client as GoogleClient;

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

        // 处理邀请关系
        $inviter = null;
        if (!empty($data['invite_code'])) {
            $inviter = User::findByInviteCode($data['invite_code']);
            if (!$inviter) {
                throw new Exception(ErrorCode::INVALID_INVITE_CODE, 'Invalid invite code');
            }
        }

        // 创建用户和邀请关系
        return DB::transaction(function () use ($data, $name, $areaCode, $inviter) {
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

            // 如果有邀请人，创建邀请关系（默认状态为 inactive）
            if ($inviter) {
                Invitation::create([
                    'inviter_id' => $inviter->id,
                    'invitee_id' => $user->id,
                ]);
            }

            // 生成 token
            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ];
        });
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
     * 重置密码（通过验证码）
     *
     * @param string $login 登录凭证（手机号或邮箱）
     * @param string $newPassword 新密码
     * @return void
     * @throws Exception
     */
    public function resetPassword(string $login, string $newPassword): void
    {
        // 判断是手机号还是邮箱
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
        
        // 查找用户
        $loginField = $isEmail ? 'email' : 'phone';
        $user = User::where($loginField, $login)->first();
        
        if (!$user) {
            throw new Exception(ErrorCode::USER_NOT_FOUND, 'User not found');
        }
        
        // 更新密码
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

    /**
     * Google 登录/注册
     *
     * @param string $idToken Google ID Token
     * @param string|null $inviteCode 邀请码
     * @param string|null $ipAddress IP 地址
     * @param string|null $userAgent User Agent
     * @return array 包含用户和 token 的数组
     * @throws Exception
     */
    public function loginWithGoogle(string $idToken, ?string $inviteCode = null, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        try {
            // 验证 Google ID Token
            $googleUser = $this->verifyGoogleIdToken($idToken);
            
            if (!$googleUser) {
                throw new Exception(ErrorCode::INVALID_CREDENTIALS, 'Invalid Google ID token');
            }

            $googleId = $googleUser['sub'];
            $email = $googleUser['email'] ?? null;
            $name = $googleUser['name'] ?? $googleUser['email'] ?? 'Google User';
            $avatar = $googleUser['picture'] ?? null;

            // 处理邀请关系
            $inviter = null;
            if (!empty($inviteCode)) {
                $inviter = User::findByInviteCode($inviteCode);
                if (!$inviter) {
                    throw new Exception(ErrorCode::INVALID_INVITE_CODE, 'Invalid invite code');
                }
            }

            // 查找或创建用户
            return DB::transaction(function () use ($googleId, $email, $name, $inviter, $ipAddress, $userAgent) {
                // 先通过 google_id 查找
                $user = User::where('google_id', $googleId)->first();
                
                // 如果不存在，通过 email 查找（可能是已存在的用户绑定 Google）
                if (!$user && $email) {
                    $user = User::where('email', $email)->first();
                    if ($user) {
                        // 绑定 Google ID
                        $user->google_id = $googleId;
                        $user->save();
                    }
                }

                // 如果还是不存在，创建新用户
                if (!$user) {
                    $user = User::create([
                        'uid' => User::generateUid(),
                        'name' => $name,
                        'email' => $email,
                        'google_id' => $googleId,
                        'password' => null, // Google 登录不需要密码
                        'status' => 'active',
                        // invite_code 会在 boot 方法中自动生成
                    ]);

                    // 如果有邀请人，创建邀请关系（默认状态为 inactive）
                    if ($inviter) {
                        Invitation::create([
                            'inviter_id' => $inviter->id,
                            'invitee_id' => $user->id,
                        ]);
                    }
                } else {
                    // 更新用户信息（如果 Google 信息有变化）
                    $updateData = [];
                    if ($name && $user->name !== $name) {
                        $updateData['name'] = $name;
                    }
                    if ($email && $user->email !== $email) {
                        $updateData['email'] = $email;
                    }
                    if (!empty($updateData)) {
                        $user->update($updateData);
                    }
                }

                // 检查用户状态
                if ($user->status !== 'active') {
                    throw new Exception(ErrorCode::ACCOUNT_DISABLED);
                }

                // 生成 token
                $token = $user->createToken('auth_token')->plainTextToken;

                // 触发登录事件
                event(new UserLoggedIn($user, $ipAddress, $userAgent));

                return [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                ];
            });
        } catch (\App\Exceptions\Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Exception(ErrorCode::INVALID_CREDENTIALS, 'Google authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * 验证 Google ID Token（使用 Google Client Library）
     *
     * @param string $idToken Google ID Token
     * @return array|null 用户信息数组，验证失败返回 null
     */
    protected function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            $clientId = config('services.google.client_id');
            
            if (!$clientId) {
                return null;
            }

            // 创建 Google Client 实例
            $client = new GoogleClient(['client_id' => $clientId]);
            
            // 验证 ID Token
            // verifyIdToken 方法会验证签名、过期时间、audience、issuer 等
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return null;
            }

            // 返回用户信息
            return [
                'sub' => $payload['sub'] ?? null,
                'email' => $payload['email'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
                'name' => $payload['name'] ?? null,
                'picture' => $payload['picture'] ?? null,
                'given_name' => $payload['given_name'] ?? null,
                'family_name' => $payload['family_name'] ?? null,
                'iss' => $payload['iss'] ?? null,
                'aud' => $payload['aud'] ?? null,
                'exp' => $payload['exp'] ?? null,
                'iat' => $payload['iat'] ?? null,
            ];
        } catch (\Exception $e) {
            // 验证失败时返回 null
            return null;
        }
    }
}

