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

    /*
     * AWS OpenSearch：始终使用 IAM SigV4（凭证走 AWS 默认链）。
     * 区域与全局 AWS 一致：AWS_DEFAULT_REGION，缺省 us-east-1。
     * SigV4 service：托管域一般为 es；OpenSearch Serverless 常为 aoss（见 AWS 文档）。
     */
    'aws_region' => env('AWS_DEFAULT_REGION') ?: 'us-east-1',

    'sigv4_service' => env('OPENSEARCH_SIGV4_SERVICE', 'es'),

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
        'deposit_created' => 'events-deposit-created',
        'deposit_completed' => 'events-deposit-completed',
        'deposit_failed' => 'events-deposit-failed',
        'first_deposit_completed' => 'events-first-deposit',
        'withdraw_created' => 'events-withdraw-created',
        'withdraw_completed' => 'events-withdraw-completed',
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

    /*
    |--------------------------------------------------------------------------
    | Index Templates
    |--------------------------------------------------------------------------
    |
    | 事件 index 模版，新建 index 时自动应用。建议先执行模版再写入数据。
    |
    */

    'index_templates' => [
        'events' => [
            'index_patterns' => ['events-*'],
            'template' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
                'mappings' => [
                    'properties' => [
                        '@timestamp' => ['type' => 'date'],
                        'event_type' => ['type' => 'keyword'],
                        'user_id' => ['type' => 'long'],
                        'uid' => ['type' => 'keyword'],
                        'email' => ['type' => 'keyword', 'ignore_above' => 256],
                        'source' => ['type' => 'keyword'],
                        'order_no' => ['type' => 'keyword'],
                        'amount' => ['type' => 'float'],
                        'currency' => ['type' => 'keyword'],
                        'event_id' => ['type' => 'keyword'],
                        'agent_id' => ['type' => 'long'],
                        'agent_link_id' => ['type' => 'long'],
                    ],
                    'dynamic_templates' => [
                        ['strings_as_keyword' => [
                            'match_mapping_type' => 'string',
                            'mapping' => ['type' => 'keyword', 'ignore_above' => 256],
                        ]],
                    ],
                ],
            ],
        ],
    ],

];
