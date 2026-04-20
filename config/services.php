<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sopay' => [
        'endpoint' => env('SOPAY_ENDPOINT'),
        'app_id' => env('SOPAY_APP_ID'),
        'app_key' => env('SOPAY_APP_KEY'),
        'callback_url' => env('SOPAY_CALLBACK_URL'),
        'return_url' => env('SOPAY_RETURN_URL'),
        'public_key' => storage_path('keys/sopay.pem'),
    ],

    'exchange_rate' => [
        'endpoint' => env('EXCHANGE_RATE_ENDPOINT'),
        'api_key' => env('EXCHANGE_RATE_API_KEY'),
    ],

    /*
    | Google reCAPTCHA Enterprise（发送验证码等）。需 GCP 项目、启用 API、服务账号具备 recaptchaenterprise 权限。
    | RECAPTCHA_ENABLED=true 且配置 project_id + site_key；鉴权见 RECAPTCHA_ENTERPRISE_CREDENTIALS 或 GOOGLE_APPLICATION_CREDENTIALS。
    | 文档：https://docs.cloud.google.com/php/docs/reference/cloud-recaptcha-enterprise/latest
    */
    'recaptcha' => [
        'enabled' => filter_var(env('RECAPTCHA_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'enterprise' => [
            'project_id' => env('RECAPTCHA_ENTERPRISE_PROJECT_ID'),
            'site_key' => env('RECAPTCHA_ENTERPRISE_SITE_KEY'),
            'credentials' => env('RECAPTCHA_ENTERPRISE_CREDENTIALS'),
        ],
        /* 仅当设置 RECAPTCHA_MIN_SCORE 时校验 RiskAnalysis.score（0~1，越高越像真人）；不配置则只校验 token 有效 */
        'min_score' => env('RECAPTCHA_MIN_SCORE'),
        'expected_action' => env('RECAPTCHA_EXPECTED_ACTION'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_ids' => [
            'ios' => env('GOOGLE_CLIENT_ID_IOS'),
            'android' => env('GOOGLE_CLIENT_ID_ANDROID'),
            'web' => env('GOOGLE_CLIENT_ID_WEB'),
        ],
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'kochava' => [
        'enabled' => env('KOCHAVA_ENABLED', false),
        'app_id' => env('KOCHAVA_APP_ID'),
    ],

    'facebook_conversions' => [
        'enabled' => env('FACEBOOK_CONVERSIONS_ENABLED', false),
        'pixel_id' => env('FACEBOOK_PIXEL_ID'),
        'access_token' => env('FACEBOOK_CONVERSIONS_ACCESS_TOKEN'),
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
    ],

    /*
    | Customer.io Track API (https://customer.io/docs/api/track/)
    | Basic auth: Site ID + API Key from workspace Settings → API Credentials
    */
    'customer_io' => [
        'enabled' => env('CUSTOMER_IO_ENABLED', false),
        'site_id' => env('CUSTOMER_IO_SITE_ID'),
        'api_key' => env('CUSTOMER_IO_API_KEY'),
        /*
         * 入站 Webhook：X-CIO-Signature + X-CIO-Timestamp（v0/SHA256）
         * 仅本地/联调可设 CUSTOMER_IO_WEBHOOK_VERIFY_SIGNATURE=false
         * @see https://customer.io/docs/journeys/webhooks-action/#securely-verify-requests
         */
        'webhook' => [
            'enabled' => env('CUSTOMER_IO_WEBHOOK_ENABLED', false),
            'signing_secret' => env('CUSTOMER_IO_WEBHOOK_SIGNING_SECRET'),
            'verify_signature' => env('CUSTOMER_IO_WEBHOOK_VERIFY_SIGNATURE', true),
        ],
    ],

];
