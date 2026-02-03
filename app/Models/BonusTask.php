<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusTask extends Model
{
    // 状态常量
    const STATUS_PENDING = 'pending';        // 待激活
    const STATUS_ACTIVE = 'active';          // 进行中
    const STATUS_COMPLETED = 'completed';    // 已完成（可领取）
    const STATUS_CLAIMED = 'claimed';        // 已领取
    const STATUS_EXPIRED = 'expired';        // 已过期
    const STATUS_CANCELLED = 'cancelled';   // 已取消
    const STATUS_DEPLETED = 'depleted';      // bonus 余额已用完但未完成任务

    protected $fillable = [
        'user_id',
        'task_no',
        'bonus_name',
        'cap_bonus',
        'base_bonus',
        'last_bonus',
        'need_wager',
        'wager',
        'status',
        'currency',
        'expired_at',
    ];

    protected $casts = [
        'cap_bonus' => 'decimal:4',
        'base_bonus' => 'decimal:4',
        'last_bonus' => 'decimal:4',
        'need_wager' => 'decimal:4',
        'wager' => 'decimal:4',
        'expired_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 是否待激活
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 是否进行中
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 是否已完成（可领取）
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 是否已领取
     */
    public function isClaimed(): bool
    {
        return $this->status === self::STATUS_CLAIMED;
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        if (!$this->expired_at) {
            return false;
        }
        return $this->expired_at->isPast();
    }

    /**
     * 是否 bonus 余额已用完
     */
    public function isDepleted(): bool
    {
        return $this->status === self::STATUS_DEPLETED;
    }

    /**
     * 检查是否可领取（已完成即可领取，不受过期时间限制）
     */
    public function isClaimable(): bool
    {
        // 只有 completed 状态可以领取，depleted 状态不能领取
        return $this->isCompleted();
    }

    /**
     * 检查是否可以操作（pending/active 状态的任务未过期才能操作）
     */
    public function canOperate(): bool
    {
        // completed 和 claimed 状态可以操作（已完成的任务不受过期限制）
        if ($this->isCompleted() || $this->isClaimed()) {
            return true;
        }
        
        // depleted 状态不能操作
        if ($this->isDepleted()) {
            return false;
        }
        
        // pending 和 active 状态需要检查是否过期
        if ($this->isPending() || $this->isActive()) {
            return !$this->isExpired();
        }
        
        return false;
    }

    /**
     * 获取完成进度百分比
     */
    public function getProgressPercent(): float
    {
        if ($this->need_wager <= 0) {
            return 100;
        }
        return min(100, ($this->wager / $this->need_wager) * 100);
    }

    /**
     * 获取可用 bonus 余额
     */
    public function getAvailableBonus(): float
    {
        return (float) $this->last_bonus;
    }

    /**
     * 检查余额是否足够
     */
    public function hasSufficientBonus(float $amount): bool
    {
        return $this->last_bonus >= $amount;
    }

    /**
     * Scope to filter completed tasks (claimable).
     * 已完成的任务即可领取，不受过期时间限制
     */
    public function scopeClaimable($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to order by created_at desc.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * 计算可领取金额
     */
    public function getClaimAmount(): float
    {
        return min((float) $this->cap_bonus, (float) $this->last_bonus);
    }
}
