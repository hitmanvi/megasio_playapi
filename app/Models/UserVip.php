<?php

namespace App\Models;

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
     * VIP level constants.
     */
    const LEVEL_BRONZE = '1';
    const LEVEL_SILVER = '2';
    const LEVEL_GOLD = '3';
    const LEVEL_PLATINUM = '4';
    const LEVEL_DIAMOND = '5';

    /**
     * VIP level configuration.
     * Maps level name to required experience points.
     */
    private static array $levelConfig = [
        self::LEVEL_BRONZE => 0,
        self::LEVEL_SILVER => 500,
        self::LEVEL_GOLD => 2000,
        self::LEVEL_PLATINUM => 5000,
        self::LEVEL_DIAMOND => 10000,
    ];

    /**
     * Get all VIP levels.
     */
    public static function getLevels(): array
    {
        return array_keys(self::$levelConfig);
    }

    /**
     * Get level configuration.
     */
    public static function getLevelConfig(): array
    {
        return self::$levelConfig;
    }

    /**
     * Get required exp for a level.
     */
    public static function getRequiredExp(string $level): int
    {
        return self::$levelConfig[$level] ?? 0;
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
        $newLevel = $this->calculateLevelFromExp($this->exp);
        
        if ($newLevel !== $this->level) {
            $this->level = $newLevel;
            $this->save();
        }
    }

    /**
     * Calculate VIP level from experience points.
     */
    private function calculateLevelFromExp(int $exp): string
    {
        $levels = self::$levelConfig;
        
        // Sort levels by exp requirement (descending)
        arsort($levels);
        
        // Find the highest level the user qualifies for
        foreach ($levels as $level => $requiredExp) {
            if ($exp >= $requiredExp) {
                return $level;
            }
        }
        
        // Default to bronze if no level matches
        return self::LEVEL_BRONZE;
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
     * Get next level information.
     */
    public function getNextLevelInfo(): ?array
    {
        $levels = self::getLevels();
        $currentIndex = array_search($this->level, $levels);
        
        if ($currentIndex === false || $currentIndex >= count($levels) - 1) {
            return null; // Already at max level
        }
        
        $nextLevel = $levels[$currentIndex + 1];
        $requiredExp = self::getRequiredExp($nextLevel);
        $expNeeded = $requiredExp - $this->exp;
        
        return [
            'level' => $nextLevel,
            'required_exp' => $requiredExp,
            'exp_needed' => $expNeeded,
            'progress_percentage' => $requiredExp > 0 
                ? round(($this->exp / $requiredExp) * 100, 2) 
                : 0,
        ];
    }
}
