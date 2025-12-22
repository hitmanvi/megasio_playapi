<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'color',
        'description',
        'enabled',
        'sort_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'sort_id' => 'integer',
    ];

    /**
     * 关联的用户
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_tags')
            ->withPivot('created_at');
    }

    /**
     * Scope: 只获取启用的
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: 按排序排列
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_id', 'asc')->orderBy('id', 'asc');
    }

    /**
     * 格式化输出
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'color' => $this->color,
            'description' => $this->description,
        ];
    }
}

