<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenSearch Connection (兼容 AWS OpenSearch)
    |--------------------------------------------------------------------------
    |
    | 用于统计事件的 OpenSearch 连接配置。
    | 不同事件写入不同 index，不同 index 使用不同模版，支持多 index 聚合查询。
    |
    */

    'enabled' => env('OPENSEARCH_ENABLED', false),

    'hosts' => array_filter(explode(',', env('OPENSEARCH_HOSTS', 'http://localhost:9200'))),

    'username' => env('OPENSEARCH_USERNAME'),

    'password' => env('OPENSEARCH_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | 所有 index 的前缀，便于区分环境（如 playapi-dev-events-*）
    |
    */

    'index_prefix' => env('OPENSEARCH_INDEX_PREFIX', 'playapi'),

    /*
    |--------------------------------------------------------------------------
    | Event Index Mapping
    |--------------------------------------------------------------------------
    |
    | 事件类型与 index 名称的映射。
    | 不同事件写入不同 index，后续可扩展更多事件类型。
    |
    */

    'event_indices' => [
        'user_registered' => 'events-user-registered',
        'user_logged_in' => 'events-user-login',
        'deposit_created' => 'events-deposit',
        'deposit_completed' => 'events-deposit',
        'first_deposit_completed' => 'events-first-deposit',
        'withdraw_completed' => 'events-withdraw',
        'order_completed' => 'events-order',
        'balance_changed' => 'events-balance',
        'vip_level_upgraded' => 'events-vip',
        'bonus_task_completed' => 'events-bonus-task',
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Timeout
    |--------------------------------------------------------------------------
    */

    'connect_timeout' => (int) env('OPENSEARCH_CONNECT_TIMEOUT', 5),

    'request_timeout' => (int) env('OPENSEARCH_REQUEST_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | 启用后输出调试日志到 Log::debug
    |
    */

    'debug' => env('OPENSEARCH_DEBUG', false),

];
