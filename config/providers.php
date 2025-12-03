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

    'return_url' => env('PROVIDER_RETURN_URL', 'https://your-domain.com/return'),

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
        | Funky Provider Currency Configuration
        |--------------------------------------------------------------------------
        |
        | 不同货币的配置，直接以 currency 为 key
        |
        */
        'USD' => [
            'api_url' => env('FUNKY_USD_API_URL', 'https://api.funky.com'),
            'client_id' => env('FUNKY_USD_CLIENT_ID'),
            'client_secret' => env('FUNKY_USD_CLIENT_SECRET'),
            'lang' => env('FUNKY_USD_LANG', 'en'),
            'funky_id' => env('FUNKY_USD_ID'),
            'funky_secret' => env('FUNKY_USD_SECRET'),
        ],

        'EUR' => [
            'api_url' => env('FUNKY_EUR_API_URL', 'https://api.funky.com'),
            'client_id' => env('FUNKY_EUR_CLIENT_ID'),
            'client_secret' => env('FUNKY_EUR_CLIENT_SECRET'),
            'lang' => env('FUNKY_EUR_LANG', 'en'),
            'funky_id' => env('FUNKY_EUR_ID'),
            'funky_secret' => env('FUNKY_EUR_SECRET'),
        ],
    ],
];

