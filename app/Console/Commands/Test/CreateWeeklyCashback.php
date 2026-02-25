<?php

namespace App\Console\Commands\Test;

use App\Models\User;
use App\Models\WeeklyCashback;
use App\Services\WeeklyCashbackService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateWeeklyCashback extends Command
{
    protected $signature = 'test:create-weekly-cashback
                            {user_id : 用户ID}
                            {--currency=USD : 货币类型}
                            {--wager=1000 : 投注额}
                            {--rate=0.05 : 返现比例}';

    protected $description = '为指定用户创建当前周期、上一周期、上上一周期的 weekly cashback 记录（测试用）';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $currency = $this->option('currency');
        $wager = (float) $this->option('wager');
        $rate = (float) $this->option('rate');

        $user = User::find($userId);
        if (!$user) {
            $this->error("用户 ID {$userId} 不存在");
            return Command::FAILURE;
        }

        if ($wager <= 0) {
            $this->error("投注额必须大于 0");
            return Command::FAILURE;
        }

        if ($rate < 0 || $rate > 1) {
            $this->error("返现比例应在 0~1 之间");
            return Command::FAILURE;
        }

        $service = new WeeklyCashbackService();
        $now = Carbon::now();

        $periods = [
            ['date' => $now, 'label' => '当前周期', 'status' => WeeklyCashback::STATUS_ACTIVE],
            ['date' => $now->copy()->subWeek(), 'label' => '上一周期', 'status' => WeeklyCashback::STATUS_CLAIMABLE],
            ['date' => $now->copy()->subWeeks(2), 'label' => '上上一周期', 'status' => WeeklyCashback::STATUS_EXPIRED],
        ];

        $rows = [];
        foreach ($periods as $p) {
            $period = $service->dateToPeriod($p['date']);
            $payout = $wager * 0.9; // 示例派彩
            $amount = $wager * $rate;

            $cashback = WeeklyCashback::updateOrCreate(
                [
                    'user_id' => $userId,
                    'period' => $period,
                    'currency' => $currency,
                ],
                [
                    'wager' => $wager,
                    'payout' => $payout,
                    'status' => $p['status'],
                    'rate' => $rate,
                    'amount' => $amount,
                ]
            );

            $rows[] = [
                $cashback->no,
                $p['label'],
                $period,
                $p['status'],
                (float) $cashback->wager,
                (float) $cashback->amount,
            ];
        }

        $this->info("成功创建/更新 weekly cashback 记录:");
        $this->table(
            ['no', '周期', 'period', 'status', 'wager', 'amount'],
            $rows
        );

        return Command::SUCCESS;
    }
}
