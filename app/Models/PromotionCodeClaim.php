<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCodeClaim extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id',
        'promotion_code_id',
        'status',
        'claimed_at',
        'expired_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function promotionCode(): BelongsTo
    {
        return $this->belongsTo(PromotionCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 领取记录是否在 expired_at 之前仍有效（null 表示不设此项时限）
     */
    public function isRecordExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }
}
