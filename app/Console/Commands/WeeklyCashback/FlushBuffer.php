<?php

namespace App\Console\Commands\WeeklyCashback;

use App\Services\WeeklyCashbackService;
use Illuminate\Console\Command;

class FlushBuffer extends Command
{
    protected $signature = 'weekly-cashback:flush-buffer';

    protected $description = '将 weekly cashback 缓冲数据刷入数据库';

    public function handle(WeeklyCashbackService $service): int
    {
        $flushed = $service->flushBuffer();
        $this->info("已刷入 {$flushed} 条 weekly cashback 缓冲");
        return Command::SUCCESS;
    }
}
