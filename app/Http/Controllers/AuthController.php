<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Services\AuthService;
use App\Services\VerificationCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    protected AuthService $authService;
    protected VerificationCodeService $verificationCodeService;

    public function __construct(AuthService $authService, VerificationCodeService $verificationCodeService)
    {
        $this->authService = $authService;
        $this->verificationCodeService = $verificationCodeService;
    }
    /**
     * 用户注册
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'string|max:255',
            'phone' => 'nullable|string|unique:users,phone',
            'area_code' => 'nullable|string|max:10',
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'invite_code' => 'nullable|string|size:8',
        ], [
            'phone.unique' => ErrorCode::PHONE_ALREADY_EXISTS->getMessage(),
            'email.unique' => ErrorCode::EMAIL_ALREADY_EXISTS->getMessage(),
        ]);

        try {
            $result = $this->authService->register([
                'name' => $request->name,
                'phone' => $request->phone,
                'area_code' => $request->area_code,
                'email' => $request->email,
                'password' => $request->password,
                'invite_code' => $request->invite_code,
            ]);

            return $this->responseItem($result);
        } catch (\App\Exceptions\Exception $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        }
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

        try {
            $result = $this->authService->login(
                $request->login,
                $request->password,
                $request->ip(),
                $request->userAgent()
            );

            return $this->responseItem($result);
        } catch (\App\Exceptions\Exception $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        }
    }

    /**
     * 用户登出
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->responseItem(null);
    }


    /**
     * 刷新用户令牌
     */
    public function refresh(Request $request): JsonResponse
    {
        $result = $this->authService->refreshToken($request->user());

        return $this->responseItem($result);
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

        try {
            $this->authService->changePassword(
                $request->user(),
                $request->current_password,
                $request->password
            );

            return $this->responseItem(null);
        } catch (\App\Exceptions\Exception $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        }
    }

    /**
     * 发送验证码
     */
    public function sendVerificationCode(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required_without:email|string',
            'email' => 'required_without:phone|string|email',
            'area_code' => 'nullable|string|max:10|required_with:phone',
            'type' => 'nullable|string|in:register,login,reset_password,default',
        ]);

        $phone = $request->input('phone');
        $email = $request->input('email');
        $areaCode = $request->input('area_code');
        $type = $request->input('type', 'default');

        // 处理 area_code，移除 + 号
        if ($areaCode && str_starts_with($areaCode, '+')) {
            $areaCode = substr($areaCode, 1);
        }

        try {
            // 根据提供的是手机号还是邮箱，调用对应的方法
            if (!empty($email)) {
                $result = $this->verificationCodeService->sendEmailCode($email, $type);
            } else {
                $result = $this->verificationCodeService->sendSmsCode($phone, $areaCode, $type);
            }
            return $this->responseItem($result);
        } catch (\App\Exceptions\Exception $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (\Exception $e) {
            return $this->error(ErrorCode::SMS_SEND_FAILED, $e->getMessage());
        }
    }

    /**
     * 生成 JWT token（用于 WebSocket 认证）
     */
    public function generateJwtToken(Request $request): JsonResponse
    {
        $result = $this->authService->generateJwtToken($request->user());

        return $this->responseItem($result);
    }
}
