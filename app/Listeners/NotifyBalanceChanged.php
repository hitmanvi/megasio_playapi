<?php

namespace App\Listeners;

use App\Events\BalanceChanged;
use App\Jobs\SendWebSocketMessage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyBalanceChanged implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(BalanceChanged $event): void
    {
        try {
            // 按需查询用户，只在需要时查询
            $user = User::find($event->userId);
            if (!$user) {
                Log::warning('User not found for balance changed notification', [
                    'user_id' => $event->userId,
                ]);
                return;
            }

            // 准备 WebSocket 消息数据
            $data = [
                'currency' => $event->balance->currency,
                'available' => (string) $event->balance->available,
                'frozen' => (string) $event->balance->frozen,
                'amount' => (string) $event->amount,
                'operation' => $event->operation,
                'type' => $event->type,
                'updated_at' => $event->balance->updated_at->toIso8601String(),
            ];

            // 分发 WebSocket 推送任务
            SendWebSocketMessage::dispatch(
                $user->uid,
                'balance.changed',
                $data
            );
        } catch (\Exception $e) {
            // 记录错误但不影响主流程
            Log::warning('Failed to notify balance changed via WebSocket', [
                'user_id' => $event->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(BalanceChanged $event, \Throwable $exception): void
    {
        Log::error('Failed to notify balance changed after retries', [
            'user_id' => $event->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
