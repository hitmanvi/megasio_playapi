<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusTask extends Model
{
    // 状态常量
    const STATUS_PENDING = 0;      // 进行中
    const STATUS_COMPLETED = 1;    // 已完成
    const STATUS_CLAIMED = 2;      // 已领取
    const STATUS_EXPIRED = 3;      // 已过期
    const STATUS_CANCELLED = 4;    // 已取消

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
    ];

    protected $casts = [
        'cap_bonus' => 'decimal:4',
        'base_bonus' => 'decimal:4',
        'last_bonus' => 'decimal:4',
        'need_wager' => 'decimal:4',
        'wager' => 'decimal:4',
        'status' => 'integer',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 是否进行中
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
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
        $this->wager = min($this->wager + $amount, $this->need_wager);
        
        // 检查是否完成
        if ($this->wager >= $this->need_wager && $this->isPending()) {
            $this->status = self::STATUS_COMPLETED;
        }
        
        $this->save();
    }
}
