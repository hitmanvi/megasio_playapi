<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Models\CheckInReward;
use App\Models\Currency;
use App\Models\UserCheckIn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    const REWARD_CYCLE = 7; // 奖励周期天数

    /**
     * 用户签到
     */
    public function checkIn(int $userId): UserCheckIn
    {
        $today = Carbon::today();

        // 获取最后一次签到记录
        $lastCheckIn = UserCheckIn::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();

        // 检查是否满足24小时间隔
        if ($lastCheckIn && $lastCheckIn->created_at) {
            $nextAvailableTime = $lastCheckIn->created_at->copy()->addHours(24);
            if ($nextAvailableTime->isFuture()) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Check-in available after ' . $nextAvailableTime->toIso8601String());
            }
        }

        // 检查今日是否已签到
        if ($lastCheckIn && $lastCheckIn->check_in_date->isToday()) {
            throw new Exception(ErrorCode::VALIDATION_ERROR, 'Already checked in today');
        }

        return DB::transaction(function () use ($userId, $today) {
            // 计算连续签到天数
            $consecutiveDays = $this->calculateConsecutiveDays($userId, $today);

            // 计算奖励档位（1-7循环）
            $rewardDay = $this->calculateRewardDay($consecutiveDays);

            // 先创建签到记录
            $checkIn = UserCheckIn::create([
                'user_id' => $userId,
                'check_in_date' => $today,
                'consecutive_days' => $consecutiveDays,
                'reward_day' => $rewardDay,
            ]);

            // 发放奖励（需要 checkIn ID 来记录交易）
            $rewardsGranted = $this->grantRewards($userId, $rewardDay, $checkIn->id);

            // 更新签到记录的奖励信息
            $checkIn->update(['rewards_granted' => $rewardsGranted]);

            return $checkIn;
        });
    }

    /**
     * 计算奖励档位（循环周期制）
     */
    protected function calculateRewardDay(int $consecutiveDays): int
    {
        return (($consecutiveDays - 1) % self::REWARD_CYCLE) + 1;
    }

    /**
     * 发放奖励
     */
    protected function grantRewards(int $userId, int $rewardDay, int $checkInId): array
    {
        $rewardConfig = CheckInReward::getRewardForDay($rewardDay);

        if (!$rewardConfig || empty($rewardConfig->rewards)) {
            return [];
        }

        $granted = [];

        foreach ($rewardConfig->rewards as $reward) {
            $type = $reward['type'] ?? '';
            $amount = (float) ($reward['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            // 根据奖励类型发放
            $this->grantRewardByType($userId, $type, $amount, $checkInId);

            $granted[] = [
                'type' => $type,
                'amount' => $amount,
                'description' => $reward['description'] ?? null,
            ];
        }

        return $granted;
    }

    /**
     * 根据类型发放奖励
     * 注意：货币类型奖励现在通过 BonusTask 来发放，不再直接操作 balance
     */
    protected function grantRewardByType(int $userId, string $type, float $amount, int $checkInId): void
    {
        // 货币类型奖励现在通过 BonusTask 来发放
        // 其他类型奖励暂不处理，可扩展
        // 例如：exp -> VipService, item -> InventoryService 等
    }

    /**
     * 判断是否为货币类型
     */
    protected function isCurrencyType(string $type): bool
    {
        return Currency::where('code', $type)->where('enabled', true)->exists();
    }

    /**
     * 计算连续签到天数
     */
    protected function calculateConsecutiveDays(int $userId, Carbon $today): int
    {
        $yesterday = $today->copy()->subDay();

        // 查找昨天的签到记录
        $yesterdayCheckIn = UserCheckIn::where('user_id', $userId)
            ->where('check_in_date', $yesterday)
            ->first();

        if ($yesterdayCheckIn) {
            // 昨天有签到，连续天数 +1
            return $yesterdayCheckIn->consecutive_days + 1;
        }

        // 昨天没签到，重新开始计数
        return 1;
    }

    /**
     * 获取用户签到状态
     */
    public function getStatus(int $userId): array
    {
        $today = Carbon::today();

        // 获取最后一次签到记录
        $lastCheckIn = UserCheckIn::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();

        // 今日签到记录
        $todayCheckIn = $lastCheckIn && $lastCheckIn->check_in_date->isToday() ? $lastCheckIn : null;

        // 获取当前连续签到天数
        $consecutiveDays = 0;
        if ($todayCheckIn) {
            $consecutiveDays = $todayCheckIn->consecutive_days;
        } else if ($lastCheckIn && $lastCheckIn->check_in_date->isYesterday()) {
            $consecutiveDays = $lastCheckIn->consecutive_days;
        }

        // 统计总签到天数
        $totalDays = UserCheckIn::where('user_id', $userId)->count();

        // 计算下次可签到时间（上次签到后24小时）
        $nextCheckInAt = $this->calculateNextCheckInAt($lastCheckIn);

        return [
            'checked_in_today' => $todayCheckIn !== null,
            'consecutive_days' => $consecutiveDays,
            'total_days' => $totalDays,
            'next_check_in_at' => $nextCheckInAt,
            'today_check_in' => $todayCheckIn ? $this->formatCheckIn($todayCheckIn) : null,
        ];
    }

    /**
     * 计算下次可签到时间
     */
    protected function calculateNextCheckInAt(?UserCheckIn $lastCheckIn): ?string
    {
        if (!$lastCheckIn || !$lastCheckIn->created_at) {
            return null; // 没有签到记录，现在可签到
        }

        $nextTime = $lastCheckIn->created_at->copy()->addHours(24);

        if ($nextTime->isPast()) {
            return null; // 已过签到时间，现在可签到
        }

        return $nextTime->toIso8601String();
    }

    /**
     * 获取用户签到历史（分页）
     */
    public function getHistory(int $userId, int $perPage = 20)
    {
        $paginator = UserCheckIn::where('user_id', $userId)
            ->orderByDesc('check_in_date')
            ->paginate($perPage);

        // 格式化数据
        $paginator->getCollection()->transform(fn($checkIn) => $this->formatCheckIn($checkIn));

        return $paginator;
    }

    /**
     * 格式化签到记录
     */
    public function formatCheckIn(UserCheckIn $checkIn): array
    {
        return [
            'id' => $checkIn->id,
            'check_in_date' => $checkIn->check_in_date->format('Y-m-d'),
            'consecutive_days' => $checkIn->consecutive_days,
            'reward_day' => $checkIn->reward_day,
            'rewards_granted' => $checkIn->rewards_granted,
            'created_at' => $checkIn->created_at?->toIso8601String(),
        ];
    }
}

