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
        'exp' => 'integer',
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
    public static function getRequiredExp(string $level): int
    {
        return VipLevel::getRequiredExp($level);
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
    public function addExp(int $exp): void
    {
        $this->exp += $exp;
        $this->save();
        
        // Check if level should be upgraded
        $this->checkLevelUp();
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
    public function isLevel(string $level): bool
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
    public function isLevelAtLeast(string $level): bool
    {
        return $this->getLevelRank() >= self::getLevelRankFor($level);
    }

    /**
     * Get level rank for a specific level.
     */
    private static function getLevelRankFor(string $level): int
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
        
        $requiredExp = $nextLevel['required_exp'];
        $expNeeded = max(0, $requiredExp - $this->exp);
        $currentLevelExp = VipLevel::getRequiredExp($this->level);
        $expRange = $requiredExp - $currentLevelExp;
        $currentProgress = $this->exp - $currentLevelExp;
        
        return [
            'level' => $nextLevel['level'],
            'group' => $nextLevel['group'],
            'required_exp' => $requiredExp,
            'exp_needed' => $expNeeded,
            'progress_percentage' => $expRange > 0 
                ? round(($currentProgress / $expRange) * 100, 2) 
                : 0,
        ];
    }
}
