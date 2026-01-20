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
     * 检查是否可领取（已完成即可领取，不受过期时间限制）
     */
    public function isClaimable(): bool
    {
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
        
        // pending 和 active 状态需要检查是否过期
        if ($this->isPending() || $this->isActive()) {
            return !$this->isExpired();
        }
        
        return false;
    }

    /**
     * 激活任务
     */
    public function activate(): void
    {
        if ($this->isPending()) {
            $this->status = self::STATUS_ACTIVE;
            $this->save();
        }
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
     * 增加流水
     */
    public function addWager(float $amount): void
    {
        // 如果任务已过期，更新状态为过期并直接返回
        if ($this->isExpired() && ($this->isPending() || $this->isActive())) {
            $this->status = self::STATUS_EXPIRED;
            $this->save();
            return;
        }
        
        $this->wager = min($this->wager + $amount, $this->need_wager);
        
        // 检查是否完成
        if ($this->wager >= $this->need_wager && $this->isActive()) {
            $this->status = self::STATUS_COMPLETED;
        }
        
        $this->save();
    }

    /**
     * 扣减 bonus 余额（下注）
     */
    public function deductBonus(float $amount): bool
    {
        if (!$this->canOperate()) {
            return false;
        }
        
        if ($this->last_bonus < $amount) {
            return false;
        }
        
        $this->last_bonus -= $amount;
        $this->save();
        
        return true;
    }

    /**
     * 增加 bonus 余额（赢钱）
     */
    public function addBonus(float $amount): void
    {
        if (!$this->canOperate()) {
            return;
        }
        
        $this->last_bonus = $this->last_bonus + $amount;
        $this->save();
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

    /**
     * 领取奖励
     * 已完成的任务即可领取，不受过期时间限制
     * 
     * @return float 领取的金额
     */
    public function claim(): float
    {
        if (!$this->isClaimable()) {
            throw new \Exception('Bonus task is not claimable');
        }
        
        $claimAmount = $this->getClaimAmount();
        $this->status = self::STATUS_CLAIMED;
        $this->save();
        
        return $claimAmount;
    }
}
