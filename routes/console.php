<?php

use App\Console\Commands\Invitation\GenerateRewards;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ========== 每日任务 ==========
// 每天清理过期的游戏提供商 Token
Schedule::command('tokens:clean-expired')->daily();

// 每天检查并更新过期的 bonus task 状态为 expired
Schedule::command('bonus-task:expire')->daily();

// 每天归档一个月前的订单
Schedule::command('orders:archive')->daily();

// 每天生成邀请奖励（基于前一天的 wager），每天凌晨2点执行
Schedule::command(GenerateRewards::class)->dailyAt('02:00');

// 每天运行一次 Funky 游戏同步
Schedule::command('import:funky_games')->daily();

// 每分钟将 weekly cashback 缓冲刷入数据库
Schedule::command('weekly-cashback:flush-buffer')->everyMinute();

// 每周一 02:00 计算 weekly cashback（将上周 active 记录计算 rate/amount 并标记为 claimable）
Schedule::command('weekly-cashback:calculate')->weeklyOn(1, '02:00');
