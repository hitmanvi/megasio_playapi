<?php

namespace App\Jobs;

use App\Services\WebSocketService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWebSocketMessage implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 用户 UID
     */
    public string $uid;

    /**
     * 事件名称
     */
    public string $event;

    /**
     * 消息数据
     */
    public mixed $data;

    /**
     * Create a new job instance.
     */
    public function __construct(string $uid, string $event, mixed $data)
    {
        $this->uid = $uid;
        $this->event = $event;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(WebSocketService $webSocketService): void
    {
        try {
            $webSocketService->sendToUser($this->uid, $this->event, $this->data);
        } catch (\Exception $e) {
            Log::error('SendWebSocketMessage job failed', [
                'uid' => $this->uid,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendWebSocketMessage job failed after retries', [
            'uid' => $this->uid,
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }
}
