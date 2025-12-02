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

        'CNY' => [
            'api_url' => env('FUNKY_CNY_API_URL', 'https://api.funky.com'),
            'client_id' => env('FUNKY_CNY_CLIENT_ID'),
            'client_secret' => env('FUNKY_CNY_CLIENT_SECRET'),
            'lang' => env('FUNKY_CNY_LANG', 'zh-CN'),
            'funky_id' => env('FUNKY_CNY_ID'),
            'funky_secret' => env('FUNKY_CNY_SECRET'),
        ],

        'JPY' => [
            'api_url' => env('FUNKY_JPY_API_URL', 'https://api.funky.com'),
            'client_id' => env('FUNKY_JPY_CLIENT_ID'),
            'client_secret' => env('FUNKY_JPY_CLIENT_SECRET'),
            'lang' => env('FUNKY_JPY_LANG', 'ja'),
            'funky_id' => env('FUNKY_JPY_ID'),
            'funky_secret' => env('FUNKY_JPY_SECRET'),
        ],

        'KRW' => [
            'api_url' => env('FUNKY_KRW_API_URL', 'https://api.funky.com'),
            'client_id' => env('FUNKY_KRW_CLIENT_ID'),
            'client_secret' => env('FUNKY_KRW_CLIENT_SECRET'),
            'lang' => env('FUNKY_KRW_LANG', 'ko'),
            'funky_id' => env('FUNKY_KRW_ID'),
            'funky_secret' => env('FUNKY_KRW_SECRET'),
        ],

        'GBP' => [
            'api_url' => env('FUNKY_GBP_API_URL', 'https://api.funky.com'),
            'client_id' => env('FUNKY_GBP_CLIENT_ID'),
            'client_secret' => env('FUNKY_GBP_CLIENT_SECRET'),
            'lang' => env('FUNKY_GBP_LANG', 'en'),
            'funky_id' => env('FUNKY_GBP_ID'),
            'funky_secret' => env('FUNKY_GBP_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Other Providers
    |--------------------------------------------------------------------------
    |
    | 可以在这里添加其他游戏提供商的配置
    |
    */
    'netflix' => [
        'default' => [
            'api_url' => env('NETFLIX_API_URL', 'https://api.netflix.com'),
            'api_key' => env('NETFLIX_API_KEY'),
            'secret' => env('NETFLIX_SECRET'),
            'demo_url' => env('NETFLIX_DEMO_URL', 'https://demo.netflix.com'),
        ],
    ],
];

