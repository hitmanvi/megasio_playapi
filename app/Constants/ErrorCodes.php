<?php

namespace App\Constants;

/**
 * 错误码常量
 * 与 ErrorCode 枚举保持一致
 */
class ErrorCodes
{
    // 成功
    public const SUCCESS = 0;

    // 通用错误 (1000-1999)
    public const VALIDATION_ERROR = 1001;
    public const UNAUTHORIZED = 1002;
    public const FORBIDDEN = 1003;
    public const NOT_FOUND = 1004;
    public const INTERNAL_ERROR = 1005;

    // 认证相关错误 (2000-2999)
    public const INVALID_CREDENTIALS = 2001;
    public const ACCOUNT_DISABLED = 2002;
    public const TOKEN_EXPIRED = 2003;
    public const TOKEN_INVALID = 2004;
    public const REGISTRATION_FAILED = 2005;
    public const LOGIN_REQUIRED = 2006;
    public const CURRENT_PASSWORD_INCORRECT = 2007;

    // 用户相关错误 (3000-3999)
    public const USER_NOT_FOUND = 3001;
    public const USER_ALREADY_EXISTS = 3002;
    public const PHONE_ALREADY_EXISTS = 3003;
    public const EMAIL_ALREADY_EXISTS = 3004;
    public const PHONE_EMAIL_REQUIRED = 3005;

    // 业务逻辑错误 (4000-4999)
    public const INSUFFICIENT_PERMISSIONS = 4001;
    public const RESOURCE_LOCKED = 4002;
    public const OPERATION_NOT_ALLOWED = 4003;
}
