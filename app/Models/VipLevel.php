<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VipLevel extends Model
{
    /** 无配置或经验不足时的默认等级 */
    public const DEFAULT_LEVEL = 1;

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
        'level' => 'integer',
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
     * Scope: 启用的
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: 排序（按 level 自然增长排序）
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('level');
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

        $data = [
            'level' => $this->level,
            'required_exp' => $this->required_exp,
            'description' => $this->description,
            'benefits' => $this->benefits,
        ];

        // 添加 group 信息（如果存在）
        if ($this->group) {
            $data['group'] = $this->group->toApiArray();
        } else {
            $data['group'] = null;
        }

        return $data;
    }

    /**
     * 所有启用等级按 level 排序后的 level 数字列表
     *
     * @return list<int>
     */
    public static function levelKeys(): array
    {
        return static::enabled()
            ->ordered()
            ->pluck('level')
            ->map(fn ($level) => (int) $level)
            ->values()
            ->all();
    }

    /**
     * 指定等级所需经验（启用配置中不存在则 0）
     */
    public static function requiredExpFor(int $level): int
    {
        $row = static::enabled()->where('level', $level)->first();

        return $row ? (int) $row->required_exp : 0;
    }

    /**
     * 指定等级的 API 结构（含 group、benefits）
     */
    public static function infoForLevel(int $level): ?array
    {
        $row = static::enabled()->where('level', $level)->with('group')->first();

        return $row?->toApiArray();
    }

    /**
     * 所有启用等级（toApiArray），按 level 升序
     *
     * @return list<array<string, mixed>>
     */
    public static function allEnabledApiArrays(): array
    {
        return static::enabled()
            ->with('group')
            ->ordered()
            ->get()
            ->map(fn (self $l) => $l->toApiArray())
            ->values()
            ->all();
    }

    /**
     * 根据累计经验计算当前等级（按 required_exp 从高到低匹配）
     */
    public static function calculateLevelFromExp(float $exp): int
    {
        $levels = static::allEnabledApiArrays();

        if ($levels === []) {
            return self::DEFAULT_LEVEL;
        }

        usort($levels, fn ($a, $b) => $b['required_exp'] <=> $a['required_exp']);

        foreach ($levels as $level) {
            if ($exp >= $level['required_exp']) {
                return (int) $level['level'];
            }
        }

        return self::DEFAULT_LEVEL;
    }
}

