<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationReward extends Model
{

    // 奖励来源类型
    const SOURCE_TYPE_DEPOSIT = 'deposit';    // 被邀请人充值奖励
    const SOURCE_TYPE_BET = 'bet';
    const SOURCE_TYPE_VIP = 'vip';

    // 发放状态
    const STATUS_PENDING = 'pending';  // 未发放
    const STATUS_PAID = 'paid';       // 已发放

    protected $fillable = [
        'user_id',
        'invitation_id',
        'source_type',
        'reward_type',
        'reward_amount',
        'wager',
        'related_id',
        'status',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:8',
        'wager' => 'decimal:8',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // 当奖励状态更新为已发放时，自动更新邀请关系的奖励总额
        static::updated(function ($reward) {
            if ($reward->invitation_id && $reward->reward_amount > 0 && $reward->isPaid()) {
                // 检查是否从 pending 变为 paid（避免重复累加）
                if ($reward->wasChanged('status') && $reward->getOriginal('status') === self::STATUS_PENDING) {
                    $reward->invitation->increment('total_reward', $reward->reward_amount);
                }
            }
        });

        // 当创建已发放的奖励时，自动更新邀请关系的奖励总额
        static::created(function ($reward) {
            if ($reward->invitation_id && $reward->reward_amount > 0 && $reward->isPaid()) {
                $reward->invitation->increment('total_reward', $reward->reward_amount);
            }
        });
    }

    /**
     * 获得奖励的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联的邀请记录
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * 检查是否已发放
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * 检查是否未发放
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
