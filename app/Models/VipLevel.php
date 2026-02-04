<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class VipLevel extends Model
{
    /**
     * Cache key for VIP levels
     */
    const CACHE_KEY = 'vip_levels';
    const CACHE_TTL = 3600; // 1 hour

    protected $fillable = [
        'group_id',
        'level',
        'required_exp',
        'description',
        'benefits',
        'sort_id',
        'enabled',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'required_exp' => 'integer',
        'benefits' => 'array',
        'sort_id' => 'integer',
        'enabled' => 'boolean',
    ];

    /**
     * 关联VIP等级组
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(VipLevelGroup::class, 'group_id');
    }

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        // 数据变更时清除缓存
        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    /**
     * 获取所有启用的等级配置（带缓存）
     */
    public static function getAllCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::where('enabled', true)
                ->with('group')
                ->orderBy('sort_id')
                ->get()
                ->map(function ($level) {
                    return $level->toApiArray();
                })
                ->keyBy('level')
                ->toArray();
        });
    }

    /**
     * 获取等级配置（带缓存）
     */
    public static function getLevelCached(string $level): ?array
    {
        $levels = self::getAllCached();
        return $levels[$level] ?? null;
    }

    /**
     * 获取所有等级标识（带缓存）
     */
    public static function getLevelKeys(): array
    {
        return array_keys(self::getAllCached());
    }

    /**
     * 获取等级所需经验值
     */
    public static function getRequiredExp(string $level): int
    {
        $levelConfig = self::getLevelCached($level);
        return $levelConfig['required_exp'] ?? 0;
    }

    /**
     * 根据经验值计算等级
     */
    public static function calculateLevelFromExp(int $exp): string
    {
        $levels = self::getAllCached();
        
        // 按经验值倒序排列
        uasort($levels, fn($a, $b) => $b['required_exp'] <=> $a['required_exp']);
        
        foreach ($levels as $levelKey => $level) {
            if ($exp >= $level['required_exp']) {
                return $levelKey;
            }
        }
        
        // 默认返回第一个等级
        $firstLevel = array_key_first(self::getAllCached());
        return $firstLevel ?? '1';
    }

    /**
     * 获取下一等级信息
     */
    public static function getNextLevel(string $currentLevel): ?array
    {
        $levels = self::getAllCached();
        $levelKeys = array_keys($levels);
        $currentIndex = array_search($currentLevel, $levelKeys);
        
        if ($currentIndex === false || $currentIndex >= count($levelKeys) - 1) {
            return null; // 已是最高等级
        }
        
        $nextLevelKey = $levelKeys[$currentIndex + 1];
        return $levels[$nextLevelKey];
    }

    /**
     * 获取默认等级（第一个等级）
     */
    public static function getDefaultLevel(): string
    {
        $levels = self::getAllCached();
        return array_key_first($levels) ?? '1';
    }

    /**
     * 清除缓存
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Scope: 启用的
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: 排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_id');
    }

    /**
     * 转换为 API 数组
     */
    public function toApiArray(): array
    {
        // 确保 group 关系已加载
        if (!$this->relationLoaded('group')) {
            $this->load('group');
        }

        return [
            'level' => $this->level,
            'required_exp' => $this->required_exp,
            'description' => $this->description,
            'benefits' => $this->benefits,
            'group' => $this->group->toApiArray(),
        ];
    }
}

