<?php

namespace App\Console\Commands\WeeklyCashback;

use App\Models\WeeklyCashback;
use App\Services\NotificationService;
use App\Services\WeeklyCashbackService;
use Illuminate\Console\Command;

class RemindUnclaimed extends Command
{
    protected $signature = 'weekly-cashback:remind-unclaimed';

    protected $description = '检查 claimable 未领取的 weekly cashback，发送提醒通知';

    public function handle(): int
    {
        $weeklyCashbackService = new WeeklyCashbackService();
        if (!$weeklyCashbackService->isWeeklyCashbackEnabled()) {
            $this->info('weekly_cashback 未开启，跳过');
            return Command::SUCCESS;
        }

        $claimables = WeeklyCashback::where('status', WeeklyCashback::STATUS_CLAIMABLE)->get();

        $notificationService = new NotificationService();
        $count = 0;
        foreach ($claimables as $cashback) {
            $notificationService->createWeeklyCashbackReminderNotification(
                $cashback->user_id,
                (float) $cashback->amount,
                $cashback->currency,
                $cashback->no,
                $cashback->period
            );
            $count++;
        }

        $this->info("已向 {$count} 条未领取的 cashback 对应用户发送提醒通知");

        return Command::SUCCESS;
    }
}
