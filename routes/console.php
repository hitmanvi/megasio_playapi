<?php

use App\Console\Commands\Invitation\GenerateRewards;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ========== 定时任务 ==========
Schedule::command('tokens:clean-expired')->daily();
Schedule::command('bonus-task:expire')->daily();
Schedule::command('orders:archive')->daily();
Schedule::command(GenerateRewards::class)->dailyAt('02:00');
Schedule::command('import:funky_games')->daily();
Schedule::command('weekly-cashback:flush-buffer')->everyMinute();
Schedule::command('weekly-cashback:calculate')->weeklyOn(1, '02:00');
Schedule::command('weekly-cashback:remind-unclaimed')->weeklyOn(4, '00:00');
