<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Models\UserActivity;
use App\Services\UserActivityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RecordUserLoginActivity implements ShouldQueue
{
    use InteractsWithQueue;

    protected UserActivityService $userActivityService;

    /**
     * Create the event listener.
     */
    public function __construct(UserActivityService $userActivityService)
    {
        $this->userActivityService = $userActivityService;
    }

    /**
     * Handle the event.
     */
    public function handle(UserLoggedIn $event): void
    {
        try {
            $this->userActivityService->createActivity(
                $event->user->id,
                UserActivity::TYPE_LOGIN,
                'User logged in',
                $event->ipAddress,
                $event->userAgent
            );
        } catch (\Exception $e) {
            // 记录活动失败不影响登录流程，只记录日志
            Log::warning('Failed to record login activity', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage()
            ]);
            
            // 如果处理失败，可以重新尝试
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(UserLoggedIn $event, \Throwable $exception): void
    {
        Log::error('Failed to record login activity after retries', [
            'user_id' => $event->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}
