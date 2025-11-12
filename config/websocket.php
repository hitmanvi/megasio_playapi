<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket PubSub Configuration
    |--------------------------------------------------------------------------
    |
    | 配置 WebSocket 的 Redis PubSub channel key
    |
    */

    'pubsub' => [
        // 广播消息的 channel key
        'broadcast' => env('WEBSOCKET_PUBSUB_BROADCAST', 'websocket:broadcast'),
        
        // 私有消息的 channel key 前缀（实际 key 为 {prefix}:{uid}）
        'private_prefix' => env('WEBSOCKET_PUBSUB_PRIVATE_PREFIX', 'websocket:private'),
    ],
];

