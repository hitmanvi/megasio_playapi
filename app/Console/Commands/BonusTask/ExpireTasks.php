<?php

namespace App\Console\Commands\BonusTask;

use App\Models\BonusTask;
use Illuminate\Console\Command;

class ExpireTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bonus-task:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查并更新过期的 bonus task 状态为 expired';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updated = BonusTask::query()
            ->whereIn('status', [BonusTask::STATUS_PENDING, BonusTask::STATUS_ACTIVE])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->update(['status' => BonusTask::STATUS_EXPIRED]);

        $this->info("已将 {$updated} 个过期的 bonus task 更新为 expired 状态");

        return Command::SUCCESS;
    }
}
