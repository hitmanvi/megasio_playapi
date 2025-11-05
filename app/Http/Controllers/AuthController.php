<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Enums\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * 用户注册
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'string|max:255',
            'phone' => 'nullable|string|unique:users,phone',
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ], [
            'phone.unique' => ErrorCode::PHONE_ALREADY_EXISTS->getMessage(),
            'email.unique' => ErrorCode::EMAIL_ALREADY_EXISTS->getMessage(),
        ]);

        // 至少需要提供手机号或邮箱其中一个
        if (empty($request->phone) && empty($request->email)) {
            return $this->error(ErrorCode::PHONE_EMAIL_REQUIRED, [
                'phone' => ['Phone number and email cannot both be empty'],
                'email' => ['Phone number and email cannot both be empty'],
            ]);
        }

        $name = $request->name ?? ($request->phone ?? $request->email);

        $user = User::create([
            'uid' => User::generateUid(), // 使用ULID生成唯一标识
            'name' => $name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->responseItem([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * 用户登录
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string', // 可以是手机号或邮箱
            'password' => 'required',
        ], [
            'login.required' => 'Please enter phone number or email',
            'password.required' => 'Please enter password',
        ]);

        // 判断是手机号还是邮箱
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        // 检查用户是否存在
        $user = User::where($loginField, $request->login)->first();
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error(ErrorCode::INVALID_CREDENTIALS);
        }

        // 检查用户状态
        if ($user->status !== 'active') {
            return $this->error(ErrorCode::ACCOUNT_DISABLED);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->responseItem([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * 用户登出
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->responseItem(null);
    }

    /**
     * 获取当前用户信息
     */
    public function mine(Request $request): JsonResponse
    {
        return $this->responseItem($request->user());
    }

    /**
     * 刷新用户令牌
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // 删除当前令牌
        $request->user()->currentAccessToken()->delete();
        
        // 创建新令牌
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->responseItem([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * 修改密码
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error(ErrorCode::CURRENT_PASSWORD_INCORRECT);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return $this->responseItem(null);
    }
}
