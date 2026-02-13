<?php

namespace App\Models;

use App\Events\VipLevelUpgraded;
use App\Services\BalanceService;
use App\Services\VipService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVip extends Model
{
    /**
     * 获取 VipService 实例
     */
    protected function getVipService(): VipService
    {
        return new VipService();
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'level',
        'exp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'integer',
        'exp' => 'decimal:4',
    ];

    /**
     * Get all VIP levels.
     */
    public static function getLevels(): array
    {
        $service = new VipService();
        return $service->getLevelKeys();
    }

    /**
     * Get required exp for a level.
     */
    public static function getRequiredExp(int $level): float
    {
        $service = new VipService();
        return (float) $service->getRequiredExp($level);
    }

    /**
     * Get the user that owns the VIP.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add experience points.
     */
    public function addExp(float $exp): void
    {
        $this->exp += $exp;
        $this->save();
        
        // Check if level should be upgraded
        $this->checkLevelUp();
    }

    /**
     * 根据订单金额和币种计算应获得的经验值
     * 
     * @param float $amount 订单金额
     * @param string $currency 币种
     * @return float 应获得的经验值
     */
    public static function calculateExpFromOrder(float $amount, string $currency): float
    {
        // 目前只支持 USD: 1 USD = 1 EXP
        if ($currency === 'USD') {
            return $amount;
        }
        
        // 其他币种暂时返回 0，后续可以扩展
        return 0.0;
    }

    /**
     * Check and upgrade level based on experience.
     */
    private function checkLevelUp(): void
    {
        $vipService = $this->getVipService();
        $newLevel = $vipService->calculateLevelFromExp((float) $this->exp);
        
        if ($newLevel !== $this->level) {
            $oldLevel = $this->level;
            $this->level = $newLevel;
            $this->save();
            
            // 确保 user 关系已加载
            if (!$this->relationLoaded('user')) {
                $this->load('user');
            }
            
            // 检查新等级的 benefits 中是否有 level_up_bonus，如果有则发放奖励
            $this->processLevelUpBonus($newLevel);
            
            // 触发 VIP 升级事件
            if ($this->user) {
                event(new VipLevelUpgraded($this->user, $oldLevel, $newLevel));
            }
        }
    }

    /**
     * 处理等级提升奖励
     * 检查新等级的 benefits 中是否有 level_cash_bonus，如果有则发放奖励
     *
     * @param int $level 新等级
     * @return void
     */
    private function processLevelUpBonus(int $level): void
    {
        if (!$this->user) {
            return;
        }

        $vipService = $this->getVipService();
        $levelInfo = $vipService->getLevelInfo($level);
        
        if (!$levelInfo || !isset($levelInfo['benefits']) || !is_array($levelInfo['benefits'])) {
            return;
        }

        $benefits = $levelInfo['benefits'];
        
        // 检查是否有 level_cash_bonus
        if (!isset($benefits['level_cash_bonus']) || empty($benefits['level_cash_bonus'])) {
            return;
        }

        $levelCashBonus = $benefits['level_cash_bonus'];
        
        // level_cash_bonus 可以是数字（金额）或数组（包含 amount 和 currency）
        $amount = 0;
        $currency = config('app.currency', 'USD');
        
        if (is_numeric($levelCashBonus)) {
            // 如果是数字，直接作为金额
            $amount = (float) $levelCashBonus;
        } elseif (is_array($levelCashBonus)) {
            // 如果是数组，提取 amount 和 currency
            $amount = isset($levelCashBonus['amount']) ? (float) $levelCashBonus['amount'] : 0;
            $currency = isset($levelCashBonus['currency']) ? $levelCashBonus['currency'] : config('app.currency', 'USD');
        } else {
            // 其他格式不支持
            return;
        }

        // 如果金额大于 0，发放奖励
        if ($amount > 0) {
            $balanceService = new BalanceService();
            $balanceService->vipLevelUpReward(
                $this->user_id,
                $currency,
                $amount,
                $level
            );
        }
    }

    /**
     * Check if user is at a specific level.
     */
    public function isLevel(int $level): bool
    {
        return $this->level === $level;
    }

    /**
     * Get level rank (numeric value for comparison).
     */
    public function getLevelRank(): int
    {
        $levels = self::getLevels();
        $rank = array_search($this->level, $levels);
        return $rank !== false ? $rank + 1 : 0;
    }

    /**
     * Check if user level is higher than or equal to the given level.
     */
    public function isLevelAtLeast(int $level): bool
    {
        return $this->getLevelRank() >= self::getLevelRankFor($level);
    }

    /**
     * Get level rank for a specific level.
     */
    private static function getLevelRankFor(int $level): int
    {
        $levels = self::getLevels();
        $rank = array_search($level, $levels);
        return $rank !== false ? $rank + 1 : 0;
    }

    /**
     * Get current level info.
     */
    public function getCurrentLevelInfo(): ?array
    {
        $vipService = $this->getVipService();
        return $vipService->getLevel($this->level);
    }

    /**
     * Get current level benefits.
     */
    public function getBenefits(): array
    {
        $levelInfo = $this->getCurrentLevelInfo();
        return $levelInfo['benefits'] ?? [];
    }

    /**
     * Get next level information.
     */
    public function getNextLevelInfo(): ?array
    {
        $vipService = $this->getVipService();
        $nextLevel = $vipService->getNextLevel($this->level);
        
        if (!$nextLevel) {
            return null; // Already at max level
        }
        
        $requiredExp = (float) $nextLevel['required_exp'];
        $expNeeded = max(0, $requiredExp - (float) $this->exp);
        $currentLevelExp = (float) $vipService->getRequiredExp($this->level);
        $expRange = $requiredExp - $currentLevelExp;
        $currentProgress = (float) $this->exp - $currentLevelExp;
        
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
