<?php

namespace App\Services;

use App\Models\BonusTask;
use App\Models\Deposit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    protected SettingService $settingService;

    public function __construct()
    {
        $this->settingService = new SettingService();
    }

    /**
     * 处理充值奖励（首充、二充、三充，或每日充值奖励）
     *
     * @param Deposit $deposit
     * @return BonusTask|null
     */
    public function processDepositBonus(Deposit $deposit): ?BonusTask
    {
        $depositAmount = (float) $deposit->amount;

        // 统计用户已经获得过首充/二充/三充奖励的次数
        $rewardedCount = BonusTask::where('user_id', $deposit->user_id)
            ->whereIn('task_no', ['FIRST_DEPOSIT_BONUS', 'SECOND_DEPOSIT_BONUS', 'THIRD_DEPOSIT_BONUS'])
            ->count();

        // 如果已完成首充/二充/三充奖励，则处理每日充值奖励
        if ($rewardedCount >= 3) {
            return $this->processDailyDepositBonus($deposit);
        }

        // 处理首充/二充/三充奖励
        $configKeys = ['first_deposit_bonus', 'second_deposit_bonus', 'third_deposit_bonus'];
        $configKey = $configKeys[$rewardedCount];

        // 获取奖励配置
        $bonusConfig = $this->settingService->getValue($configKey);

        // 检查配置是否存在且已启用
        if (!$bonusConfig || !isset($bonusConfig['enabled']) || !$bonusConfig['enabled']) {
            return null;
        }

        // 检查是否已经给过该次数的奖励
        $taskNo = strtoupper($configKey);
        $existingTask = BonusTask::where('user_id', $deposit->user_id)
            ->where('task_no', $taskNo)
            ->first();

        if ($existingTask) {
            // 已经给过该次数的奖励，跳过
            return null;
        }

        // 检查充值金额是否满足 amounts 阶梯
        $amounts = $bonusConfig['amounts'] ?? [];
        $ratios = $bonusConfig['ratio'] ?? [];

        if (empty($amounts) || empty($ratios) || count($amounts) !== count($ratios)) {
            return null;
        }

        // 找到满足的最高阶梯
        $matchedTier = $this->findMatchedTier($depositAmount, $amounts);

        if ($matchedTier === -1) {
            // 充值金额不满足任何阶梯，跳过
            return null;
        }

        // 计算奖励金额
        $finalBonusAmount = $this->calculateBonusAmount($depositAmount, $ratios[$matchedTier], $bonusConfig);

        if ($finalBonusAmount <= 0) {
            return null;
        }

        $currency = $bonusConfig['currency'] ?? config('app.currency', 'USD');

        // 获取流水倍数配置，默认 40
        $wagerMultiplier = (float) config('app.deposit_bonus_wager_multiplier', 40);

        // 计算需要的流水 = 奖励金额 * 倍数
        $needWager = $finalBonusAmount * $wagerMultiplier;

        // 生成奖励名称
        $bonusName = $this->getBonusName($rewardedCount);

        // 创建 BonusTask
        return DB::transaction(function () use ($deposit, $taskNo, $bonusName, $finalBonusAmount, $currency, $needWager) {
            return BonusTask::create([
                'user_id' => $deposit->user_id,
                'task_no' => $taskNo,
                'bonus_name' => $bonusName,
                'cap_bonus' => $finalBonusAmount,
                'base_bonus' => $finalBonusAmount,
                'last_bonus' => $finalBonusAmount,
                'need_wager' => $needWager,
                'wager' => 0,
                'status' => BonusTask::STATUS_PENDING,
                'currency' => $currency,
            ]);
        });
    }

    /**
     * 处理每日充值奖励
     *
     * @param Deposit $deposit
     * @return BonusTask|null
     */
    protected function processDailyDepositBonus(Deposit $deposit): ?BonusTask
    {
        $depositAmount = (float) $deposit->amount;
        $today = Carbon::today();

        // 获取每日充值奖励配置
        $bonusConfig = $this->settingService->getValue('daily_deposit_bonus');

        // 检查配置是否存在且已启用
        if (!$bonusConfig || !isset($bonusConfig['enabled']) || !$bonusConfig['enabled']) {
            return null;
        }

        // 检查当天已经给过的次数（限制每天三次）
        $maxTimes = (int) ($bonusConfig['times'] ?? 3);
        $todayTaskCount = BonusTask::where('user_id', $deposit->user_id)
            ->where('task_no', 'LIKE', 'DAILY_DEPOSIT_BONUS_%')
            ->whereDate('created_at', $today)
            ->count();

        if ($todayTaskCount >= $maxTimes) {
            // 当天已经给过最大次数，跳过
            return null;
        }

        // 检查充值金额是否满足 amounts 阶梯
        $amounts = $bonusConfig['amounts'] ?? [];
        $ratios = $bonusConfig['ratio'] ?? [];

        if (empty($amounts) || empty($ratios) || count($amounts) !== count($ratios)) {
            return null;
        }

        // 找到满足的最高阶梯
        $matchedTier = $this->findMatchedTier($depositAmount, $amounts);

        if ($matchedTier === -1) {
            // 充值金额不满足任何阶梯，跳过
            return null;
        }

        // 计算奖励金额
        $finalBonusAmount = $this->calculateBonusAmount($depositAmount, $ratios[$matchedTier], $bonusConfig);

        if ($finalBonusAmount <= 0) {
            return null;
        }

        $currency = $bonusConfig['currency'] ?? config('app.currency', 'USD');

        // 获取流水倍数配置，默认 40
        $wagerMultiplier = (float) config('app.deposit_bonus_wager_multiplier', 40);

        // 计算需要的流水 = 奖励金额 * 倍数
        $needWager = $finalBonusAmount * $wagerMultiplier;

        // 生成任务编号（包含日期，用于区分不同天的奖励）
        $taskNo = 'DAILY_DEPOSIT_BONUS_' . $today->format('Ymd') . '_' . ($todayTaskCount + 1);

        // 生成奖励名称
        $bonusName = 'Daily Deposit Bonus (' . ($todayTaskCount + 1) . '/' . $maxTimes . ')';

        // 创建 BonusTask
        return DB::transaction(function () use ($deposit, $taskNo, $bonusName, $finalBonusAmount, $currency, $needWager) {
            return BonusTask::create([
                'user_id' => $deposit->user_id,
                'task_no' => $taskNo,
                'bonus_name' => $bonusName,
                'cap_bonus' => $finalBonusAmount,
                'base_bonus' => $finalBonusAmount,
                'last_bonus' => $finalBonusAmount,
                'need_wager' => $needWager,
                'wager' => 0,
                'status' => BonusTask::STATUS_PENDING,
                'currency' => $currency,
            ]);
        });
    }

    /**
     * 找到满足的最高阶梯
     *
     * @param float $depositAmount
     * @param array $amounts
     * @return int 阶梯索引，-1 表示不满足任何阶梯
     */
    protected function findMatchedTier(float $depositAmount, array $amounts): int
    {
        for ($i = count($amounts) - 1; $i >= 0; $i--) {
            if ($depositAmount >= (float) $amounts[$i]) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * 计算奖励金额
     *
     * @param float $depositAmount
     * @param float $ratio
     * @param array $bonusConfig
     * @return float
     */
    protected function calculateBonusAmount(float $depositAmount, float $ratio, array $bonusConfig): float
    {
        // 计算奖励金额 = 充值金额 * ratio / 100，但不能超过 max_bonus_amount
        $bonusAmount = ($depositAmount * $ratio) / 100;
        $maxBonusAmount = (float) ($bonusConfig['max_bonus_amount'] ?? 0);
        
        return min($bonusAmount, $maxBonusAmount);
    }

    /**
     * 获取奖励名称
     *
     * @param int $rewardedCount
     * @return string
     */
    protected function getBonusName(int $rewardedCount): string
    {
        return match ($rewardedCount) {
            0 => 'First Deposit Bonus',
            1 => 'Second Deposit Bonus',
            2 => 'Third Deposit Bonus',
            default => 'Deposit Bonus',
        };
    }

    /**
     * 获取用户充值奖励状态（返回当前能获得的第一个奖励）
     *
     * @param int $userId
     * @return array
     */
    public function getDepositBonusStatus(int $userId): array
    {
        $today = Carbon::today();

        // 统计用户已经获得过首充/二充/三充奖励的次数
        $rewardedCount = BonusTask::where('user_id', $userId)
            ->whereIn('task_no', ['FIRST_DEPOSIT_BONUS', 'SECOND_DEPOSIT_BONUS', 'THIRD_DEPOSIT_BONUS'])
            ->count();

        // 如果首充/二充/三充都完成了，返回每日充值奖励状态
        if ($rewardedCount >= 3) {
            return $this->getDailyDepositBonusStatus($userId, $today);
        }

        // 返回当前可以获得的第一个奖励（首充/二充/三充）
        $configKeys = ['first_deposit_bonus', 'second_deposit_bonus', 'third_deposit_bonus'];
        $configKey = $configKeys[$rewardedCount];
        $bonusConfig = $this->settingService->getValue($configKey);

        $taskNo = strtoupper($configKey);
        $existingTask = BonusTask::where('user_id', $userId)
            ->where('task_no', $taskNo)
            ->first();

        $result = [
            'type' => 'first_second_third',
            'current_reward' => [
                'type' => $configKey,
                'name' => $this->getBonusName($rewardedCount),
                'completed' => $existingTask !== null,
                'config' => null,
            ],
        ];

        if ($bonusConfig && isset($bonusConfig['enabled']) && $bonusConfig['enabled']) {
            $result['current_reward']['config'] = [
                'amounts' => $bonusConfig['amounts'] ?? [],
                'ratio' => $bonusConfig['ratio'] ?? [],
                'max_bonus_amount' => (float) ($bonusConfig['max_bonus_amount'] ?? 0),
                'currency' => $bonusConfig['currency'] ?? config('app.currency', 'USD'),
            ];
        }

        return $result;
    }

    /**
     * 获取每日充值奖励状态
     *
     * @param int $userId
     * @param Carbon $today
     * @return array
     */
    protected function getDailyDepositBonusStatus(int $userId, Carbon $today): array
    {
        $dailyBonusConfig = $this->settingService->getValue('daily_deposit_bonus');

        if (!$dailyBonusConfig || !isset($dailyBonusConfig['enabled']) || !$dailyBonusConfig['enabled']) {
            return [
                'type' => 'daily',
                'current_reward' => [
                    'enabled' => false,
                ],
            ];
        }

        $maxTimes = (int) ($dailyBonusConfig['times'] ?? 3);
        $todayTaskCount = BonusTask::where('user_id', $userId)
            ->where('task_no', 'LIKE', 'DAILY_DEPOSIT_BONUS_%')
            ->whereDate('created_at', $today)
            ->count();

        return [
            'type' => 'daily',
            'current_reward' => [
                'enabled' => true,
                'today_count' => $todayTaskCount,
                'max_times' => $maxTimes,
                'remaining_times' => max(0, $maxTimes - $todayTaskCount),
                'config' => [
                    'amounts' => $dailyBonusConfig['amounts'] ?? [],
                    'ratio' => $dailyBonusConfig['ratio'] ?? [],
                    'max_bonus_amount' => (float) ($dailyBonusConfig['max_bonus_amount'] ?? 0),
                    'currency' => $dailyBonusConfig['currency'] ?? config('app.currency', 'USD'),
                ],
            ],
        ];
    }

    /**
     * 获取充值奖励配置
     *
     * @return array
     */
    public function getDepositBonusConfig(): array
    {
        $configKeys = [
            'first_deposit_bonus',
            'second_deposit_bonus',
            'third_deposit_bonus',
            'daily_deposit_bonus',
        ];

        $result = [];

        foreach ($configKeys as $configKey) {
            $config = $this->settingService->getValue($configKey);
            
            if ($config && is_array($config)) {
                // 只返回启用的配置
                if (isset($config['enabled']) && $config['enabled']) {
                    $result[$configKey] = [
                        'method' => $config['method'] ?? 'ratio',
                        'currency' => $config['currency'] ?? config('app.currency', 'USD'),
                        'amounts' => $config['amounts'] ?? [],
                        'ratio' => $config['ratio'] ?? [],
                        'max_bonus_amount' => (float) ($config['max_bonus_amount'] ?? 0),
                        'enabled' => true,
                    ];

                    // 如果是每日充值奖励，添加 times 字段
                    if ($configKey === 'daily_deposit_bonus' && isset($config['times'])) {
                        $result[$configKey]['times'] = (int) $config['times'];
                    }
                }
            }
        }

        return $result;
    }
}
