<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VipLevel extends Model
{

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
}

