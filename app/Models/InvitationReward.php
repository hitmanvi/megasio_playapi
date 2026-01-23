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


    protected $fillable = [
        'user_id',
        'invitation_id',
        'source_type',
        'reward_type',
        'reward_amount',
        'wager',
        'related_id',
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

        // 当创建奖励时，自动更新邀请关系的奖励总额
        static::created(function ($reward) {
            if ($reward->invitation_id && $reward->reward_amount > 0) {
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
}
