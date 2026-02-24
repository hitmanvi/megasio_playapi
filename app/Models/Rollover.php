<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rollover extends Model
{
    // 状态常量
    const STATUS_PENDING = 'pending';        // 待激活
    const STATUS_ACTIVE = 'active';         // 进行中
    const STATUS_COMPLETED = 'completed';   // 已完成

    // 来源类型常量
    const SOURCE_TYPE_DEPOSIT = 'deposit';   // 充值
    const SOURCE_TYPE_BONUS = 'bonus';       // 奖励
    const SOURCE_TYPE_REWARD = 'reward';     // 奖励

    protected $fillable = [
        'user_id',
        'source_type',
        'related_id',
        'currency',
        'amount',
        'required_wager',
        'current_wager',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'required_wager' => 'decimal:8',
        'current_wager' => 'decimal:8',
        'completed_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联充值订单（仅当 source_type 为 deposit 时）
     */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class, 'related_id');
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
     * 是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 获取完成进度百分比
     */
    public function getProgressPercent(): float
    {
        if ($this->required_wager <= 0) {
            return 100.0;
        }

        $percent = ($this->current_wager / $this->required_wager) * 100;
        return min(100.0, max(0.0, round($percent, 2)));
    }

    /**
     * 检查是否已完成
     */
    public function checkCompletion(): bool
    {
        if ($this->isCompleted()) {
            return true;
        }

        if ($this->current_wager >= $this->required_wager) {
            $this->status = self::STATUS_COMPLETED;
            $this->completed_at = now();
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * 获取用户未完成的 rollover 总额
     *
     * @param int $userId
     * @param string $currency
     * @return float
     */
    public static function getUncompletedTotal(int $userId, string $currency): float
    {
        return (float) self::where('user_id', $userId)
            ->where('currency', $currency)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_ACTIVE])
            ->sum('amount');
    }
}
