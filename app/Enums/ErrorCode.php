<?php

namespace App\Enums;

enum ErrorCode: int
{
    // 成功
    case SUCCESS = 0;

    // 通用错误 (1000-1999)
    case VALIDATION_ERROR = 1001;
    case UNAUTHORIZED = 1002;
    case FORBIDDEN = 1003;
    case NOT_FOUND = 1004;
    case INTERNAL_ERROR = 1005;

    // 认证相关错误 (2000-2999)
    case INVALID_CREDENTIALS = 2001;
    case ACCOUNT_DISABLED = 2002;
    case TOKEN_EXPIRED = 2003;
    case TOKEN_INVALID = 2004;
    case REGISTRATION_FAILED = 2005;
    case LOGIN_REQUIRED = 2006;
    case CURRENT_PASSWORD_INCORRECT = 2007;

    // 用户相关错误 (3000-3999)
    case USER_NOT_FOUND = 3001;
    case USER_ALREADY_EXISTS = 3002;
    case PHONE_ALREADY_EXISTS = 3003;
    case EMAIL_ALREADY_EXISTS = 3004;
    case PHONE_EMAIL_REQUIRED = 3005;

    // 业务逻辑错误 (4000-4999)
    case INSUFFICIENT_PERMISSIONS = 4001;
    case RESOURCE_LOCKED = 4002;
    case OPERATION_NOT_ALLOWED = 4003;

    /**
     * 获取错误消息
     */
    public function getMessage(): string
    {
        return match($this) {
            self::SUCCESS => 'Success',
            
            // 通用错误
            self::VALIDATION_ERROR => 'Validation failed',
            self::UNAUTHORIZED => 'Unauthorized access',
            self::FORBIDDEN => 'Access forbidden',
            self::NOT_FOUND => 'Resource not found',
            self::INTERNAL_ERROR => 'Internal server error',
            
            // 认证相关错误
            self::INVALID_CREDENTIALS => 'The provided credentials are incorrect',
            self::ACCOUNT_DISABLED => 'Account has been disabled. Please contact administrator',
            self::TOKEN_EXPIRED => 'Token has expired',
            self::TOKEN_INVALID => 'Invalid token',
            self::REGISTRATION_FAILED => 'Registration failed',
            self::LOGIN_REQUIRED => 'Login required',
            self::CURRENT_PASSWORD_INCORRECT => 'The current password is incorrect',
            
            // 用户相关错误
            self::USER_NOT_FOUND => 'User not found',
            self::USER_ALREADY_EXISTS => 'User already exists',
            self::PHONE_ALREADY_EXISTS => 'This phone number has already been registered',
            self::EMAIL_ALREADY_EXISTS => 'This email has already been registered',
            self::PHONE_EMAIL_REQUIRED => 'Please provide either phone number or email',
            
            // 业务逻辑错误
            self::INSUFFICIENT_PERMISSIONS => 'Insufficient permissions',
            self::RESOURCE_LOCKED => 'Resource is locked',
            self::OPERATION_NOT_ALLOWED => 'Operation not allowed',
        };
    }

    /**
     * 获取错误码和消息数组
     */
    public function toArray(): array
    {
        return [$this->value, $this->getMessage()];
    }

    /**
     * 根据错误码获取枚举实例
     */
    public static function fromCode(int $code): ?self
    {
        return self::tryFrom($code);
    }

    /**
     * 检查是否为成功状态
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * 检查是否为错误状态
     */
    public function isError(): bool
    {
        return $this !== self::SUCCESS;
    }
}
