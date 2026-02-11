<?php

namespace App\Models;

use App\Events\VipLevelUpgraded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVip extends Model
{
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
        return VipLevel::getLevelKeys();
    }

    /**
     * Get required exp for a level.
     */
    public static function getRequiredExp(int $level): float
    {
        return (float) VipLevel::getRequiredExp($level);
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
        $newLevel = VipLevel::calculateLevelFromExp($this->exp);
        
        if ($newLevel !== $this->level) {
            $oldLevel = $this->level;
            $this->level = $newLevel;
            $this->save();
            
            // 确保 user 关系已加载
            if (!$this->relationLoaded('user')) {
                $this->load('user');
            }
            
            // 触发 VIP 升级事件
            if ($this->user) {
                event(new VipLevelUpgraded($this->user, $oldLevel, $newLevel));
            }
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
        return VipLevel::getLevelCached($this->level);
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
        $nextLevel = VipLevel::getNextLevel($this->level);
        
        if (!$nextLevel) {
            return null; // Already at max level
        }
        
        $requiredExp = (float) $nextLevel['required_exp'];
        $expNeeded = max(0, $requiredExp - (float) $this->exp);
        $currentLevelExp = (float) VipLevel::getRequiredExp($this->level);
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
