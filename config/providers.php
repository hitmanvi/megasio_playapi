<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Game Providers Configuration
    |--------------------------------------------------------------------------
    |
    | 游戏提供商配置，支持按货币类型配置不同的参数
    |
    */

    'return_url' => env('PROVIDER_RETURN_URL'),

    /*
    |--------------------------------------------------------------------------
    | Game Provider Rate Limit Configuration
    |--------------------------------------------------------------------------
    |
    | 游戏提供商回调接口的频率限制配置
    | max_attempts: 每分钟最大请求次数
    | decay_minutes: 限制周期（分钟）
    |
    */
    'rate_limit' => [
        'max_attempts' => env('GP_RATE_LIMIT_MAX_ATTEMPTS', 1000),
        'decay_minutes' => env('GP_RATE_LIMIT_DECAY_MINUTES', 1),
    ],


    'default_currency' => env('DEFAULT_CURRENCY', 'USD'),
    
    'funky' => [
        /*
        |--------------------------------------------------------------------------
        | Funky Provider IP Whitelist
        |--------------------------------------------------------------------------
        |
        | IP 白名单配置，支持单个 IP 和 CIDR 格式
        | 例如：['192.168.1.1', '10.0.0.0/8']
        |
        */
        'ip_whitelist' => env('FUNKY_IP_WHITELIST') 
            ? explode(',', env('FUNKY_IP_WHITELIST'))
            : [],

        /*
        |--------------------------------------------------------------------------
        | Funky Provider Default Configuration
        |--------------------------------------------------------------------------
        |
        | 通用配置，所有币种共享（如果某个币种没有特定配置，则使用此配置）
        | 可以通过环境变量 FUNKY_API_URL, FUNKY_CLIENT_ID 等配置
        |
        */
        'default' => [
            'api_url' => env('FUNKY_API_URL'),
            'client_id' => env('FUNKY_CLIENT_ID'),
            'client_secret' => env('FUNKY_CLIENT_SECRET'),
            'lang' => env('FUNKY_LANG', 'en'),
            'funky_id' => env('FUNKY_ID'),
            'funky_secret' => env('FUNKY_SECRET'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Funky Provider Currency-Specific Configuration
        |--------------------------------------------------------------------------
        |
        | 币种特定配置（可选），如果某个币种需要特殊配置，可以在这里定义
        | 如果某个币种没有特定配置，则使用上面的 default 配置
        |
        */
        'USD' => [
            'api_url' => env('FUNKY_API_URL'),
            'client_id' => env('FUNKY_CLIENT_ID'),
            'client_secret' => env('FUNKY_CLIENT_SECRET'),
            'lang' => env('FUNKY_LANG', 'en'),
            'funky_id' => env('FUNKY_ID'),
            'funky_secret' => env('FUNKY_SECRET'),
        ],

    ],
];

