<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    /**
     * 广播消息给所有连接的客户端
     *
     * @param string $event 事件名称
     * @param mixed $data 消息数据
     * @return bool
     */
    public function broadcast(string $event, $data): bool
    {
        try {
            $channel = config('websocket.pubsub.broadcast');
            $message = [
                'event' => $event,
                'data' => $data,
            ];

            Redis::publish($channel, json_encode($message));

            return true;
        } catch (\Exception $e) {
            Log::error('WebSocket broadcast failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 发送私有消息给指定用户
     *
     * @param string $uid 用户 UID
     * @param string $event 事件名称
     * @param mixed $data 消息数据
     * @return bool
     */
    public function sendToUser(string $uid, string $event, $data): bool
    {
        try {
            $prefix = config('websocket.pubsub.private_prefix');
            $channel = "{$prefix}:{$uid}";
            $message = [
                'event' => $event,
                'data' => $data,
            ];

            Redis::publish($channel, json_encode($message));

            return true;
        } catch (\Exception $e) {
            Log::error('WebSocket private message failed', [
                'uid' => $uid,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 发送私有消息给多个用户
     *
     * @param array $uids 用户 UID 数组
     * @param string $event 事件名称
     * @param mixed $data 消息数据
     * @return array 返回每个用户的发送结果 ['uid' => true/false]
     */
    public function sendToUsers(array $uids, string $event, $data): array
    {
        $results = [];
        foreach ($uids as $uid) {
            $results[$uid] = $this->sendToUser($uid, $event, $data);
        }
        return $results;
    }
}

