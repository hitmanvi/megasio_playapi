<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Enums\ErrorCode;
use App\Exceptions\Exception;

class VerificationCodeService
{
    /**
     * 验证码有效期（分钟）
     */
    const CODE_EXPIRE_MINUTES = 5;

    /**
     * 验证码重发间隔（秒）
     */
    const RESEND_INTERVAL = 60;

    /**
     * 验证码长度
     */
    const CODE_LENGTH = 6;

    /**
     * 发送短信验证码
     *
     * @param string $phone 手机号
     * @param string|null $areaCode 区号
     * @param string $type 验证码类型（如：register, login, reset_password）
     * @return array
     * @throws Exception
     */
    public function sendSmsCode(string $phone, ?string $areaCode = null, string $type = 'default'): array
    {
        // 验证手机号格式
        if (!$this->validatePhone($phone)) {
            throw new Exception(ErrorCode::VALIDATION_ERROR, 'Invalid phone number format');
        }

        return $this->sendCodeInternal($phone, false, $areaCode, $type, function ($code) use ($phone, $areaCode) {
            $this->sendSms($phone, $areaCode, $code);
        });
    }

    /**
     * 发送邮件验证码
     *
     * @param string $email 邮箱
     * @param string $type 验证码类型（如：register, login, reset_password）
     * @return array
     * @throws Exception
     */
    public function sendEmailCode(string $email, string $type = 'default'): array
    {
        // 验证邮箱格式
        if (!$this->validateEmail($email)) {
            throw new Exception(ErrorCode::VALIDATION_ERROR, 'Invalid email format');
        }

        return $this->sendCodeInternal($email, true, null, $type, function ($code) use ($email) {
            $this->sendEmail($email, $code);
        });
    }

    /**
     * 发送验证码（支持手机号和邮箱）- 兼容方法
     * 
     * @deprecated 建议使用 sendSmsCode() 或 sendEmailCode() 替代
     * @param string|null $phone 手机号
     * @param string|null $areaCode 区号（仅用于手机号）
     * @param string|null $email 邮箱
     * @param string $type 验证码类型（如：register, login, reset_password）
     * @return array
     * @throws Exception
     */
    public function sendCode(?string $phone = null, ?string $areaCode = null, ?string $email = null, string $type = 'default'): array
    {
        // 必须提供手机号或邮箱其中一个
        if (empty($phone) && empty($email)) {
            throw new Exception(ErrorCode::VALIDATION_ERROR, 'Phone or email is required');
        }

        // 不能同时提供手机号和邮箱
        if (!empty($phone) && !empty($email)) {
            throw new Exception(ErrorCode::VALIDATION_ERROR, 'Cannot provide both phone and email');
        }

        if (!empty($email)) {
            return $this->sendEmailCode($email, $type);
        }

        return $this->sendSmsCode($phone, $areaCode, $type);
    }

    /**
     * 发送验证码内部实现（提取公共逻辑）
     *
     * @param string $identifier 手机号或邮箱
     * @param bool $isEmail 是否为邮箱
     * @param string|null $areaCode 区号（仅用于手机号）
     * @param string $type 验证码类型
     * @param callable $sendCallback 发送回调函数
     * @return array
     * @throws Exception
     */
    protected function sendCodeInternal(string $identifier, bool $isEmail, ?string $areaCode, string $type, callable $sendCallback): array
    {
        // 检查重发间隔
        $cacheKey = $this->getResendCacheKey($identifier, $isEmail, $areaCode, $type);
        if (Cache::has($cacheKey)) {
            $remainingSeconds = Cache::get($cacheKey) - now()->timestamp;
            throw new Exception(ErrorCode::SMS_SEND_TOO_FREQUENT, "Please wait {$remainingSeconds} seconds before resending");
        }

        // 生成验证码
        $code = $this->generateCode();

        // 存储验证码到缓存
        $codeCacheKey = $this->getCodeCacheKey($identifier, $isEmail, $areaCode, $type);
        Cache::put($codeCacheKey, $code, now()->addMinutes(self::CODE_EXPIRE_MINUTES));

        // 设置重发间隔
        Cache::put($cacheKey, now()->addSeconds(self::RESEND_INTERVAL)->timestamp, now()->addSeconds(self::RESEND_INTERVAL));

        // 发送验证码
        $sendCallback($code);

        return [
            'success' => true,
            'message' => 'Verification code sent successfully',
            'expires_in' => self::CODE_EXPIRE_MINUTES * 60, // 秒
        ];
    }

    /**
     * 验证短信验证码
     *
     * @param string $phone 手机号
     * @param string $code 验证码
     * @param string|null $areaCode 区号
     * @param string $type 验证码类型
     * @return bool
     */
    public function verifySmsCode(string $phone, string $code, ?string $areaCode = null, string $type = 'default'): bool
    {
        return $this->verifyCodeInternal($phone, false, $areaCode, $code, $type);
    }

    /**
     * 验证邮件验证码
     *
     * @param string $email 邮箱
     * @param string $code 验证码
     * @param string $type 验证码类型
     * @return bool
     */
    public function verifyEmailCode(string $email, string $code, string $type = 'default'): bool
    {
        return $this->verifyCodeInternal($email, true, null, $code, $type);
    }

    /**
     * 验证验证码 - 兼容方法
     * 
     * @deprecated 建议使用 verifySmsCode() 或 verifyEmailCode() 替代
     * @param string|null $phone 手机号
     * @param string|null $areaCode 区号（仅用于手机号）
     * @param string|null $email 邮箱
     * @param string $code 验证码
     * @param string $type 验证码类型
     * @return bool
     */
    public function verifyCode(?string $phone = null, ?string $areaCode = null, ?string $email = null, string $code, string $type = 'default'): bool
    {
        // 必须提供手机号或邮箱其中一个
        if (empty($phone) && empty($email)) {
            return false;
        }

        if (!empty($email)) {
            return $this->verifyEmailCode($email, $code, $type);
        }

        return $this->verifySmsCode($phone, $code, $areaCode, $type);
    }

    /**
     * 验证验证码内部实现（提取公共逻辑）
     *
     * @param string $identifier 手机号或邮箱
     * @param bool $isEmail 是否为邮箱
     * @param string|null $areaCode 区号（仅用于手机号）
     * @param string $code 验证码
     * @param string $type 验证码类型
     * @return bool
     */
    protected function verifyCodeInternal(string $identifier, bool $isEmail, ?string $areaCode, string $code, string $type): bool
    {
        $cacheKey = $this->getCodeCacheKey($identifier, $isEmail, $areaCode, $type);
        $cachedCode = Cache::get($cacheKey);

        if (!$cachedCode) {
            return false;
        }

        if ($cachedCode !== $code) {
            return false;
        }

        // 验证成功后删除验证码
        Cache::forget($cacheKey);

        return true;
    }

    /**
     * 生成验证码
     *
     * @return string
     */
    protected function generateCode(): string
    {
        return str_pad((string)random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * 获取验证码缓存键
     *
     * @param string $identifier 手机号或邮箱
     * @param bool $isEmail 是否为邮箱
     * @param string|null $areaCode 区号
     * @param string $type 类型
     * @return string
     */
    protected function getCodeCacheKey(string $identifier, bool $isEmail, ?string $areaCode, string $type): string
    {
        $prefix = $isEmail ? 'email' : 'phone';
        $key = "verification_code:{$type}:{$prefix}:{$identifier}";
        if ($areaCode && !$isEmail) {
            $key = "verification_code:{$type}:{$prefix}:{$areaCode}:{$identifier}";
        }
        return $key;
    }

    /**
     * 获取重发间隔缓存键
     *
     * @param string $identifier 手机号或邮箱
     * @param bool $isEmail 是否为邮箱
     * @param string|null $areaCode 区号
     * @param string $type 类型
     * @return string
     */
    protected function getResendCacheKey(string $identifier, bool $isEmail, ?string $areaCode, string $type): string
    {
        $prefix = $isEmail ? 'email' : 'phone';
        $key = "verification_resend:{$type}:{$prefix}:{$identifier}";
        if ($areaCode && !$isEmail) {
            $key = "verification_resend:{$type}:{$prefix}:{$areaCode}:{$identifier}";
        }
        return $key;
    }

    /**
     * 验证手机号格式
     *
     * @param string $phone
     * @return bool
     */
    protected function validatePhone(string $phone): bool
    {
        // 基本验证：只包含数字，长度在6-15位之间
        return preg_match('/^\d{6,15}$/', $phone) === 1;
    }

    /**
     * 验证邮箱格式
     *
     * @param string $email
     * @return bool
     */
    protected function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 发送短信
     *
     * @param string $phone
     * @param string|null $areaCode
     * @param string $code
     * @return void
     */
    protected function sendSms(string $phone, ?string $areaCode, string $code): void
    {
        // TODO: 集成实际的短信服务
        // 这里应该调用第三方短信服务API，如 Twilio, AWS SNS, 阿里云短信等
        
        // 日志记录
        $fullPhone = $areaCode ? "{$areaCode}{$phone}" : $phone;
        Log::info('Verification code sent via SMS', [
            'phone' => $fullPhone,
            'code' => $code,
            'area_code' => $areaCode,
        ]);

        // 开发环境可以输出到日志
        if (config('app.env') === 'local') {
            Log::info("Verification code for {$fullPhone}: {$code}");
        }
    }

    /**
     * 发送邮件
     *
     * @param string $email
     * @param string $code
     * @return void
     */
    protected function sendEmail(string $email, string $code): void
    {
        // TODO: 集成实际的邮件服务
        // 这里应该使用 Laravel Mail 发送邮件，或者调用第三方邮件服务API
        
        // 日志记录
        Log::info('Verification code sent via Email', [
            'email' => $email,
            'code' => $code,
        ]);

        // 开发环境可以输出到日志
        if (config('app.env') === 'local') {
            Log::info("Verification code for {$email}: {$code}");
        }

        // 可以使用 Laravel Mail 发送邮件
        // Mail::to($email)->send(new VerificationCodeMail($code));
    }
}

