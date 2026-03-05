<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'parent_id',
        'name',
        'promotion_code',
        'account',
        'remark',
        'facebook_config',
        'kochava_config',
        'status',
        'two_factor_secret',
    ];

    protected $casts = [
        'facebook_config' => 'array',
        'kochava_config' => 'array',
        'two_factor_secret' => 'encrypted',
    ];

    protected $hidden = [
        'facebook_config',
        'two_factor_secret',
    ];

    /**
     * 上级 Agent
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'parent_id');
    }

    /**
     * 下级 Agent 列表
     */
    public function children(): HasMany
    {
        return $this->hasMany(Agent::class, 'parent_id');
    }

    /**
     * 获取层级深度（根为 0，每下一级 +1）
     */
    public function getLevel(): int
    {
        $level = 0;
        $agent = $this->parent;
        while ($agent) {
            $level++;
            $agent = $agent->parent;
        }
        return $level;
    }

    /**
     * 获取根 Agent（顶层）
     */
    public function getRoot(): self
    {
        $agent = $this;
        while ($agent->parent_id) {
            $agent = $agent->parent;
        }
        return $agent;
    }

    /**
     * 获取所有祖先（从父到根）
     */
    public function getAncestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $agent = $this->parent;
        while ($agent) {
            $ancestors->push($agent);
            $agent = $agent->parent;
        }
        return $ancestors;
    }

    /**
     * 获取所有后代（递归子级）
     */
    public function getDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }
        return $descendants;
    }

    /**
     * 根据推广码查找 Agent
     */
    public static function findByPromotionCode(string $code): ?self
    {
        return static::where('promotion_code', $code)->where('status', self::STATUS_ACTIVE)->first();
    }

    /**
     * 是否已配置 Facebook Conversions（需 enabled 为 true）
     */
    public function hasFacebookConversions(): bool
    {
        $cfg = $this->facebook_config ?? [];
        if (isset($cfg['enabled']) && !$cfg['enabled']) {
            return false;
        }
        return !empty($cfg['pixel_id'] ?? '') && !empty($cfg['access_token'] ?? '');
    }

    /**
     * 是否已开启二次验证（OTP/TOTP）
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->two_factor_secret);
    }

    /**
     * 是否已配置 Kochava（开关由 config services.kochava.enabled 控制）
     */
    public function hasKochava(): bool
    {
        return !empty($this->kochava_config['app_id'] ?? '');
    }

    /**
     * 获取 Facebook Conversions 配置（pixel_id, access_token, enabled）
     */
    public function getFacebookConfig(): array
    {
        return $this->facebook_config ?? [];
    }

    /**
     * 获取 Kochava 配置（app_id）
     */
    public function getKochavaConfig(): array
    {
        return $this->kochava_config ?? [];
    }
}
