<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionCode extends Model
{
    /** 领取对象：不限用户 */
    public const TARGET_TYPE_ALL = 'all';

    /** 领取对象：定向用户（名单由业务或其它数据维护） */
    public const TARGET_TYPE_USERS = 'users';

    /** 奖励类型：bonus task（暂仅支持） */
    public const BONUS_TYPE_BONUS_TASK = 'bonus_task';

    /** 兑换码状态：可领取 */
    public const STATUS_ACTIVE = 'active';

    /** 兑换码状态：停用（后台关闭） */
    public const STATUS_INACTIVE = 'inactive';

    /** 兑换码状态：次数已领完 */
    public const STATUS_EXHAUSTED = 'exhausted';

    protected $fillable = [
        'name',
        'code',
        'times',
        'claimed_count',
        'bonus_type',
        'bonus_config',
        'expired_at',
        'target_type',
        'status',
        'remark',
    ];

    protected $casts = [
        'times' => 'integer',
        'claimed_count' => 'integer',
        'bonus_config' => 'array',
        'expired_at' => 'datetime',
    ];

    /**
     * 兑换码整体是否已过期（expired_at 已到）
     */
    public function isGloballyExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    public function isActiveStatus(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactiveStatus(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isExhaustedStatus(): bool
    {
        return $this->status === self::STATUS_EXHAUSTED;
    }

    /**
     * 是否面向全体用户
     */
    public function targetsAllUsers(): bool
    {
        return $this->target_type === self::TARGET_TYPE_ALL;
    }

    public function claims(): HasMany
    {
        return $this->hasMany(PromotionCodeClaim::class);
    }

    public function customerIoCampaignLinks(): HasMany
    {
        return $this->hasMany(CustomerIoCampaignPromotionCode::class);
    }
}
