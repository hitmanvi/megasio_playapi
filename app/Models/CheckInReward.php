<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CheckInReward extends Model
{
    protected $fillable = [
        'day',
        'rewards',
        'enabled',
    ];

    protected $casts = [
        'day' => 'integer',
        'rewards' => 'array',
        'enabled' => 'boolean',
    ];

    /**
     * 启用的奖励
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * 按天数排序
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('day');
    }

    /**
     * 按天数查询
     */
    public function scopeByDay(Builder $query, int $day): Builder
    {
        return $query->where('day', $day);
    }

    /**
     * 获取指定天数的奖励
     */
    public static function getRewardForDay(int $day): ?self
    {
        return static::enabled()->byDay($day)->first();
    }

    /**
     * 获取所有奖励配置
     */
    public static function getAllRewards(): \Illuminate\Database\Eloquent\Collection
    {
        return static::enabled()->ordered()->get();
    }
}
