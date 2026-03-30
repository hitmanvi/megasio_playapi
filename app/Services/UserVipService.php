<?php

namespace App\Services;

use App\Events\VipLevelUpgraded;
use App\Models\UserVip;
use App\Models\VipLevel;

class UserVipService
{
    /**
     * 获取用户当前VIP等级
     *
     * @param int $userId
     * @return int|null
     */
    public function getLevel(int $userId): ?int
    {
        $vip = UserVip::where('user_id', $userId)->first();
        return $vip?->level;
    }
    /**
     * @return list<int>
     */
    public static function getLevels(): array
    {
        return VipLevel::levelKeys();
    }

    public static function getRequiredExp(int $level): float
    {
        return (float) VipLevel::requiredExpFor($level);
    }

    /**
     * 根据订单金额和币种计算应获得的经验值
     */
    public static function calculateExpFromOrder(float $amount, string $currency): float
    {
        if ($currency === 'USD') {
            return $amount;
        }

        return 0.0;
    }

    public function addExp(UserVip $vip, float $exp): void
    {
        $vip->exp += $exp;
        $vip->save();

        $this->checkLevelUp($vip);
    }

    private function checkLevelUp(UserVip $vip): void
    {
        $newLevel = VipLevel::calculateLevelFromExp((float) $vip->exp);

        if ($newLevel === $vip->level) {
            return;
        }

        $oldLevel = $vip->level;
        $vip->level = $newLevel;
        $vip->save();

        if (!$vip->relationLoaded('user')) {
            $vip->load('user');
        }

        $this->processLevelUpBonus($vip, $newLevel);

        if ($vip->user) {
            event(new VipLevelUpgraded($vip->user, $oldLevel, $newLevel));
        }
    }

    /**
     * 检查新等级 benefits 中的 level_cash_bonus 并发放
     */
    private function processLevelUpBonus(UserVip $vip, int $level): void
    {
        if (!$vip->user) {
            return;
        }

        $levelInfo = VipLevel::infoForLevel($level);

        if (!$levelInfo || !isset($levelInfo['benefits']) || !is_array($levelInfo['benefits'])) {
            return;
        }

        $benefits = $levelInfo['benefits'];

        if (!isset($benefits['level_cash_bonus']) || empty($benefits['level_cash_bonus'])) {
            return;
        }

        $levelCashBonus = $benefits['level_cash_bonus'];

        $amount = 0;
        $currency = config('app.currency', 'USD');

        if (is_numeric($levelCashBonus)) {
            $amount = (float) $levelCashBonus;
        } elseif (is_array($levelCashBonus)) {
            $amount = isset($levelCashBonus['amount']) ? (float) $levelCashBonus['amount'] : 0;
            $currency = $levelCashBonus['currency'] ?? config('app.currency', 'USD');
        } else {
            return;
        }

        if ($amount > 0) {
            (new BalanceService())->vipLevelUpReward(
                $vip->user_id,
                $currency,
                $amount,
                $level
            );
        }
    }

    public function isLevel(UserVip $vip, int $level): bool
    {
        return $vip->level === $level;
    }

    public function getLevelRank(UserVip $vip): int
    {
        $levels = self::getLevels();
        $rank = array_search($vip->level, $levels, true);

        return $rank !== false ? $rank + 1 : 0;
    }

    public function isLevelAtLeast(UserVip $vip, int $level): bool
    {
        return $this->getLevelRank($vip) >= $this->getLevelRankForLevel($level);
    }

    private static function getLevelRankForLevel(int $level): int
    {
        $levels = self::getLevels();
        $rank = array_search($level, $levels, true);

        return $rank !== false ? $rank + 1 : 0;
    }

    public function getCurrentLevelInfo(UserVip $vip): ?array
    {
        return VipLevel::infoForLevel($vip->level);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBenefits(UserVip $vip): array
    {
        $levelInfo = $this->getCurrentLevelInfo($vip);

        return $levelInfo['benefits'] ?? [];
    }

    /**
     * @return array{
     *     level: int,
     *     group: mixed,
     *     required_exp: float,
     *     exp_needed: float,
     *     progress_percentage: float
     * }|null
     */
    public function getNextLevelInfo(UserVip $vip): ?array
    {
        $nextLevel = VipLevel::infoForLevel($vip->level + 1);

        if (!$nextLevel) {
            return null;
        }

        $requiredExp = (float) $nextLevel['required_exp'];
        $expNeeded = max(0, $requiredExp - (float) $vip->exp);
        $currentLevelExp = (float) VipLevel::requiredExpFor($vip->level);
        $expRange = $requiredExp - $currentLevelExp;
        $currentProgress = (float) $vip->exp - $currentLevelExp;

        return [
            'level' => $nextLevel['level'],
            'group' => $nextLevel['group'] ?? null,
            'required_exp' => $requiredExp,
            'exp_needed' => $expNeeded,
            'progress_percentage' => $expRange > 0
                ? round(($currentProgress / $expRange) * 100, 2)
                : 0,
        ];
    }
}
