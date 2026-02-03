<?php

namespace App\Console\Commands\Test;

use App\Models\BonusTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateBonusTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:generate-bonus-tasks 
                            {user_id : 用户ID} 
                            {--count=10 : 生成的奖励任务数量}
                            {--min-bonus=10 : 最小奖励金额}
                            {--max-bonus=500 : 最大奖励金额}
                            {--min-wager=100 : 最小流水要求}
                            {--max-wager=5000 : 最大流水要求}
                            {--status= : 任务状态（pending, active, completed, claimed, expired, cancelled），不指定则随机}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为指定用户生成测试奖励任务数据';

    /**
     * 任务编号前缀列表
     */
    protected array $taskNoPrefixes = [
        'FIRST_DEPOSIT_BONUS',
        'SECOND_DEPOSIT_BONUS',
        'THIRD_DEPOSIT_BONUS',
        'DAILY_DEPOSIT_BONUS',
        'BONUS_TASK',
        'PROMOTION_BONUS',
        'WELCOME_BONUS',
    ];

    /**
     * 奖励名称列表
     */
    protected array $bonusNames = [
        'First Deposit Bonus',
        'Second Deposit Bonus',
        'Third Deposit Bonus',
        'Daily Deposit Bonus',
        'Welcome Bonus',
        'Promotion Bonus',
        'Special Bonus',
        'Lucky Bonus',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $count = (int) $this->option('count');
        $minBonus = (float) $this->option('min-bonus');
        $maxBonus = (float) $this->option('max-bonus');
        $minWager = (float) $this->option('min-wager');
        $maxWager = (float) $this->option('max-wager');
        $status = $this->option('status');

        // 验证用户是否存在
        $user = User::find($userId);
        if (!$user) {
            $this->error("用户 ID {$userId} 不存在");
            return Command::FAILURE;
        }

        // 验证状态参数（如果提供）
        if ($status !== null) {
            $validStatuses = [
                BonusTask::STATUS_PENDING,
                BonusTask::STATUS_ACTIVE,
                BonusTask::STATUS_COMPLETED,
                BonusTask::STATUS_CLAIMED,
                BonusTask::STATUS_EXPIRED,
                BonusTask::STATUS_CANCELLED,
            ];

            if (!in_array($status, $validStatuses)) {
                $this->error("无效的状态值: {$status}");
                $this->info("有效状态: " . implode(', ', $validStatuses));
                return Command::FAILURE;
            }
        }

        $this->info("开始为用户 {$user->name} (ID: {$userId}) 生成 {$count} 个奖励任务...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $totalGenerated = 0;
        $currency = config('app.currency', 'USD');

        for ($i = 0; $i < $count; $i++) {
            // 随机选择任务编号前缀
            $taskNoPrefix = $this->taskNoPrefixes[array_rand($this->taskNoPrefixes)];
            // 生成唯一的后缀，确保总长度不超过 50 个字符
            $suffix = strtoupper(substr(uniqid(), -8)); // 使用 uniqid 的后 8 位
            $taskNo = $taskNoPrefix . '_' . $suffix;
            
            // 确保 task_no 不超过 50 个字符
            if (strlen($taskNo) > 50) {
                $maxPrefixLength = 50 - strlen($suffix) - 1; // 减去下划线的长度
                $taskNoPrefix = substr($taskNoPrefix, 0, $maxPrefixLength);
                $taskNo = $taskNoPrefix . '_' . $suffix;
            }

            // 随机选择奖励名称
            $bonusName = $this->bonusNames[array_rand($this->bonusNames)];

            // 生成随机奖励金额
            $capBonus = $this->randomFloat($minBonus, $maxBonus);
            $baseBonus = $capBonus * $this->randomFloat(0.5, 0.9); // base_bonus 通常是 cap_bonus 的 50%-90%
            $lastBonus = $capBonus * $this->randomFloat(0.3, 1.0); // last_bonus 可以是 cap_bonus 的 30%-100%

            // 生成随机流水要求
            $needWager = $this->randomFloat($minWager, $maxWager);

            // 根据状态生成 wager（已完成的任务 wager 应该等于或接近 need_wager）
            $taskStatus = $status ?? $this->getRandomStatus();
            $wager = $this->calculateWagerByStatus($taskStatus, $needWager);

            // 生成过期时间（pending 和 active 状态的任务可能有过期时间）
            $expiredAt = $this->generateExpiredAt($taskStatus);

            // 生成随机创建时间（最近30天内）
            $createdAt = Carbon::now()
                ->subDays(rand(0, 30))
                ->subHours(rand(0, 23))
                ->subMinutes(rand(0, 59));

            DB::transaction(function () use ($userId, $taskNo, $bonusName, $capBonus, $baseBonus, $lastBonus, $needWager, $wager, $taskStatus, $currency, $expiredAt, $createdAt) {
                BonusTask::create([
                    'user_id' => $userId,
                    'task_no' => $taskNo,
                    'bonus_name' => $bonusName,
                    'cap_bonus' => $capBonus,
                    'base_bonus' => $baseBonus,
                    'last_bonus' => $lastBonus,
                    'need_wager' => $needWager,
                    'wager' => $wager,
                    'status' => $taskStatus,
                    'currency' => $currency,
                    'expired_at' => $expiredAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            });

            $totalGenerated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("成功生成 {$totalGenerated} 个奖励任务！");

        // 显示统计信息
        $stats = BonusTask::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as count, SUM(cap_bonus) as total_bonus')
            ->groupBy('status')
            ->get();

        if ($stats->isNotEmpty()) {
            $this->newLine();
            $this->info("任务统计：");
            $tableData = $stats->map(function ($stat) {
                return [
                    'status' => $stat->status,
                    'count' => $stat->count,
                    'total_bonus' => number_format((float) $stat->total_bonus, 2),
                ];
            })->toArray();

            $this->table(
                ['状态', '数量', '总奖励金额'],
                $tableData
            );
        }

        return Command::SUCCESS;
    }

    /**
     * 获取随机状态
     */
    protected function getRandomStatus(): string
    {
        $statuses = [
            BonusTask::STATUS_PENDING,
            BonusTask::STATUS_ACTIVE,
            BonusTask::STATUS_COMPLETED,
            BonusTask::STATUS_CLAIMED,
            BonusTask::STATUS_EXPIRED,
            BonusTask::STATUS_CANCELLED,
            BonusTask::STATUS_DEPLETED,
        ];

        return $statuses[array_rand($statuses)];
    }

    /**
     * 根据状态计算 wager
     */
    protected function calculateWagerByStatus(string $status, float $needWager): float
    {
        return match ($status) {
            BonusTask::STATUS_PENDING => 0, // 待激活的任务 wager 为 0
            BonusTask::STATUS_ACTIVE => $this->randomFloat(0, $needWager * 0.9), // 进行中的任务 wager 在 0-90% 之间
            BonusTask::STATUS_COMPLETED => $needWager, // 已完成的任务 wager 等于 need_wager
            BonusTask::STATUS_CLAIMED => $needWager, // 已领取的任务 wager 等于 need_wager
            BonusTask::STATUS_EXPIRED => $this->randomFloat(0, $needWager * 0.8), // 已过期的任务 wager 可能未完成
            BonusTask::STATUS_CANCELLED => $this->randomFloat(0, $needWager * 0.5), // 已取消的任务 wager 可能未完成
            BonusTask::STATUS_DEPLETED => $this->randomFloat(0, $needWager * 0.9), // depleted 状态的任务 wager 未完成
            default => 0,
        };
    }

    /**
     * 根据状态生成过期时间
     */
    protected function generateExpiredAt(?string $status): ?Carbon
    {
        // pending 和 active 状态的任务可能有过期时间
        if (in_array($status, [BonusTask::STATUS_PENDING, BonusTask::STATUS_ACTIVE])) {
            // 50% 的概率有过期时间
            if (rand(0, 1)) {
                // 过期时间在未来 1-30 天之间
                return Carbon::now()->addDays(rand(1, 30));
            }
        }

        // expired 状态的任务过期时间应该在过去
        if ($status === BonusTask::STATUS_EXPIRED) {
            return Carbon::now()->subDays(rand(1, 10));
        }

        // 其他状态的任务可能没有过期时间
        return null;
    }

    /**
     * 生成随机浮点数
     */
    protected function randomFloat(float $min, float $max): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 4);
    }
}
