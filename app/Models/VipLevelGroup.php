<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VipLevelGroup extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'card_img',
        'sort_id',
        'enabled',
    ];

    protected $casts = [
        'sort_id' => 'integer',
        'enabled' => 'boolean',
    ];

    /**
     * 关联的VIP等级
     */
    public function vipLevels(): HasMany
    {
        return $this->hasMany(VipLevel::class, 'group_id');
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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'card_img' => $this->card_img,
        ];
    }
}
