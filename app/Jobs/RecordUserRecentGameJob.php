<?php

namespace App\Jobs;

use App\Services\UserRecentGameService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordUserRecentGameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务最大尝试次数
     */
    public int $tries = 3;

    /**
     * 任务超时时间（秒）
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public int $gameId,
        public float $multiplier = 0
    ) {}

    /**
     * Execute the job.
     */
    public function handle(UserRecentGameService $service): void
    {
        $service->recordPlay($this->userId, $this->gameId, $this->multiplier);
    }
}

