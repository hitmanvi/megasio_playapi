<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每天清理过期的游戏提供商 Token
Schedule::command('tokens:clean-expired')->daily();

// 每天归档一个月前的订单
Schedule::command('orders:archive')->daily();
