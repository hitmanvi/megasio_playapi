<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Models\Deposit;
use App\Models\UserCheckIn;
use App\Services\BalanceService;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    const REWARD_CYCLE = 7; // 奖励周期天数

    protected BalanceService $balanceService;
    protected SettingService $settingService;

    public function __construct()
    {
        $this->balanceService = new BalanceService();
        $this->settingService = new SettingService();
    }

    /**
     * 用户签到
     */
    public function checkIn(int $userId, bool $isBonusCheckIn = false): UserCheckIn
    {
        $today = Carbon::today();

        // 获取今日所有签到记录
        $todayCheckIns = UserCheckIn::where('user_id', $userId)
            ->where('check_in_date', $today)
            ->get();

        // 如果是额外签到，检查是否满足条件
        if ($isBonusCheckIn) {
            // 检查是否已经使用过额外签到
            $hasBonusCheckIn = $todayCheckIns->where('is_bonus_check_in', true)->isNotEmpty();
            if ($hasBonusCheckIn) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Bonus check-in already used today');
            }

            // 检查是否满足额外签到条件（当天使用特定通道充值成功）
            if (!$this->canBonusCheckIn($userId, $today)) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Bonus check-in not available. Please deposit using eligible payment channels.');
            }
        } else {
            // 普通签到：检查今日是否已签到（不包括额外签到）
            $hasNormalCheckIn = $todayCheckIns->where('is_bonus_check_in', false)->isNotEmpty();
            if ($hasNormalCheckIn) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Already checked in today');
            }

            // 检查是否满足24小时间隔（只对普通签到检查）
            $lastCheckIn = UserCheckIn::where('user_id', $userId)
                ->orderByDesc('created_at')
                ->first();

            if ($lastCheckIn && $lastCheckIn->created_at) {
                $nextAvailableTime = $lastCheckIn->created_at->copy()->addHours(24);
                if ($nextAvailableTime->isFuture()) {
                    throw new Exception(ErrorCode::VALIDATION_ERROR, 'Check-in available after ' . $nextAvailableTime->toIso8601String());
                }
            }
        }

        return DB::transaction(function () use ($userId, $today, $isBonusCheckIn) {
            // 计算连续签到天数（额外签到不影响连续天数）
            $consecutiveDays = $isBonusCheckIn 
                ? $this->getTodayConsecutiveDays($userId, $today)
                : $this->calculateConsecutiveDays($userId, $today);

            // 计算奖励档位（1-7循环）
            $rewardDay = $this->calculateRewardDay($consecutiveDays);

            // 创建签到记录
            $checkIn = UserCheckIn::create([
                'user_id' => $userId,
                'check_in_date' => $today,
                'consecutive_days' => $consecutiveDays,
                'reward_day' => $rewardDay,
                'is_bonus_check_in' => $isBonusCheckIn,
            ]);

            // 发放奖励（需要 checkIn ID 来记录交易）
            $rewardsGranted = $this->grantRewards($userId, $rewardDay, $checkIn->id);

            // 更新签到记录的奖励信息
            $checkIn->update(['rewards_granted' => $rewardsGranted]);

            return $checkIn;
        });
    }

    /**
     * 检查是否可以额外签到（当天使用特定通道充值成功）
     */
    protected function canBonusCheckIn(int $userId, Carbon $today): bool
    {
        // 从 check_in_bonus 配置中获取额外签到配置
        $checkInBonus = $this->settingService->getValue('check_in_bonus');

        // 检查配置是否存在
        if (!$checkInBonus) {
            return false;
        }

        // 如果 payment_method_ids 为空或不存在，表示未启用额外签到功能
        if (!isset($checkInBonus['payment_method_ids']) || !is_array($checkInBonus['payment_method_ids']) || empty($checkInBonus['payment_method_ids'])) {
            return false;
        }

        $allowedPaymentIds = $checkInBonus['payment_method_ids'];

        // 检查用户当天是否使用特定通道充值成功
        $hasEligibleDeposit = Deposit::where('user_id', $userId)
            ->where('status', Deposit::STATUS_COMPLETED)
            ->whereDate('completed_at', $today)
            ->whereIn('id', $allowedPaymentIds)
            ->exists();

        return $hasEligibleDeposit;
    }

    /**
     * 获取今日的连续签到天数（用于额外签到）
     */
    protected function getTodayConsecutiveDays(int $userId, Carbon $today): int
    {
        // 获取今日的普通签到记录
        $todayNormalCheckIn = UserCheckIn::where('user_id', $userId)
            ->where('check_in_date', $today)
            ->where('is_bonus_check_in', false)
            ->first();

        if ($todayNormalCheckIn) {
            return $todayNormalCheckIn->consecutive_days;
        }

        // 如果没有普通签到，返回最后一次签到的连续天数
        return $this->calculateConsecutiveDays($userId, $today);
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
        // 从 setting 中获取签到奖励配置
        $checkInBonus = $this->settingService->getValue('check_in_bonus');

        // 检查配置是否存在且已启用
        if (!$checkInBonus || !isset($checkInBonus['enabled']) || !$checkInBonus['enabled']) {
            return [];
        }

        if (!isset($checkInBonus['rewards']) || !is_array($checkInBonus['rewards'])) {
            return [];
        }

        // rewardDay 是 1-7，rewards 数组索引是 0-6
        $rewardIndex = $rewardDay - 1;

        if (!isset($checkInBonus['rewards'][$rewardIndex])) {
            return [];
        }

        $rewardAmount = (float) $checkInBonus['rewards'][$rewardIndex];

        if ($rewardAmount <= 0) {
            return [];
        }

        $currency = $checkInBonus['currency'] ?? config('app.currency', 'USD');

        // 使用 BalanceService 增加用户余额并创建交易记录
        $this->balanceService->checkInReward($userId, $currency, $rewardAmount, $checkInId, $rewardDay);

        return [
            [
                'type' => $currency,
                'amount' => $rewardAmount,
                'description' => "Check-in reward day {$rewardDay}",
            ],
        ];
    }

    /**
     * 计算连续签到天数（不会断签，基于最后一次签到继续累加）
     */
    protected function calculateConsecutiveDays(int $userId, Carbon $today): int
    {
        // 查找最后一次签到记录（按日期排序）
        $lastCheckIn = UserCheckIn::where('user_id', $userId)
            ->orderByDesc('check_in_date')
            ->first();

        if ($lastCheckIn) {
            // 有签到记录，连续天数 = 最后一次签到的连续天数 + 1（不会断签）
            return $lastCheckIn->consecutive_days + 1;
        }

        // 没有签到记录，第一次签到
        return 1;
    }

    /**
     * 获取用户签到状态
     */
    public function getStatus(int $userId): array
    {
        $today = Carbon::today();

        // 获取今日所有签到记录
        $todayCheckIns = UserCheckIn::where('user_id', $userId)
            ->where('check_in_date', $today)
            ->get();

        // 今日普通签到记录
        $todayNormalCheckIn = $todayCheckIns->where('is_bonus_check_in', false)->first();
        
        // 今日额外签到记录
        $todayBonusCheckIn = $todayCheckIns->where('is_bonus_check_in', true)->first();

        // 获取最后一次签到记录（用于计算连续天数）
        $lastCheckIn = UserCheckIn::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();

        // 获取当前连续签到天数
        $consecutiveDays = 0;
        if ($todayNormalCheckIn) {
            $consecutiveDays = $todayNormalCheckIn->consecutive_days;
        } else if ($lastCheckIn && $lastCheckIn->check_in_date->isYesterday()) {
            $consecutiveDays = $lastCheckIn->consecutive_days;
        }

        // 统计总签到天数
        $totalDays = UserCheckIn::where('user_id', $userId)->count();

        // 计算下次可签到时间（上次签到后24小时）
        $nextCheckInAt = $this->calculateNextCheckInAt($lastCheckIn);

        // 检查是否可以额外签到
        $canBonusCheckIn = $this->canBonusCheckIn($userId, $today) && !$todayBonusCheckIn;

        return [
            'checked_in_today' => $todayNormalCheckIn !== null,
            'checked_in_bonus_today' => $todayBonusCheckIn !== null,
            'can_bonus_check_in' => $canBonusCheckIn,
            'consecutive_days' => $consecutiveDays,
            'total_days' => $totalDays,
            'next_check_in_at' => $nextCheckInAt,
            'today_check_in' => $todayNormalCheckIn ? $this->formatCheckIn($todayNormalCheckIn) : null,
            'today_bonus_check_in' => $todayBonusCheckIn ? $this->formatCheckIn($todayBonusCheckIn) : null,
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
            'is_bonus_check_in' => $checkIn->is_bonus_check_in ?? false,
            'created_at' => $checkIn->created_at?->toIso8601String(),
        ];
    }
}

