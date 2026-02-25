<?php

namespace App\Console\Commands\WeeklyCashback;

use App\Services\WeeklyCashbackService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Calculate extends Command
{
    protected $signature = 'weekly-cashback:calculate
                            {--period= : 指定周期（ISO年*100+周数），不传则默认上周}';

    protected $description = '计算 weekly cashback（将上周 active 记录计算 rate/amount 并标记为 claimable）';

    public function handle(WeeklyCashbackService $service): int
    {
        $period = $this->option('period')
            ? (int) $this->option('period')
            : $service->dateToPeriod(Carbon::now()->subWeek());

        $this->info("计算周期: {$period}");

        $count = $service->calculateAndFinalizeForPeriod($period);

        $this->info("已处理 {$count} 条 weekly cashback");

        return Command::SUCCESS;
    }
}
