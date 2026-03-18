<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLink extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /** 默认 noagent 的 promotion_code，用于无推广来源的用户 */
    public const NOAGENT_PROMOTION_CODE = 'NONE';

    /**
     * 获取默认 noagent 的 AgentLink（需先执行 php artisan init:agent）
     */
    public static function getNoAgentLink(): ?self
    {
        return static::findByPromotionCode(self::NOAGENT_PROMOTION_CODE);
    }

    protected $fillable = [
        'agent_id',
        'name',
        'promotion_code',
        'status',
        'facebook_config',
        'kochava_config',
    ];

    protected $casts = [
        'facebook_config' => 'array',
        'kochava_config' => 'array',
    ];

    protected $hidden = [
        'facebook_config',
    ];

    /**
     * 所属 Agent
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    /**
     * 根据推广码查找 AgentLink
     */
    public static function findByPromotionCode(string $code): ?self
    {
        return static::whereHas('agent', fn ($q) => $q->where('status', Agent::STATUS_ACTIVE))
            ->where('promotion_code', $code)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
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
     * 是否已配置 Kochava
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
