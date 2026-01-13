<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationReward extends Model
{

    // 奖励来源类型
    const SOURCE_TYPE_REGISTER = 'register';    // 被邀请人注册
    const SOURCE_TYPE_DEPOSIT = 'deposit';      // 被邀请人充值
    const SOURCE_TYPE_BET = 'bet';              // 被邀请人下注

    protected $fillable = [
        'user_id',
        'invitation_id',
        'source_type',
        'reward_type',
        'reward_amount',
        'related_id',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:8',
    ];

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
