<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitation extends Model
{
    // 状态常量
    const STATUS_ACTIVE = 'active';        // 活跃
    const STATUS_INACTIVE = 'inactive';    // 不活跃

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inviter_id',
        'invitee_id',
        'total_reward',
        'status',
    ];

    protected $casts = [
        'total_reward' => 'decimal:8',
    ];

    /**
     * Get the inviter (邀请人).
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /**
     * Get the invitee (被邀请人).
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    /**
     * Get the invitation rewards.
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(InvitationReward::class);
    }

    /**
     * Scope to filter by inviter.
     */
    public function scopeByInviter($query, int $inviterId)
    {
        return $query->where('inviter_id', $inviterId);
    }

    /**
     * Scope to filter by invitee.
     */
    public function scopeByInvitee($query, int $inviteeId)
    {
        return $query->where('invitee_id', $inviteeId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter active invitations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 是否活跃
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 是否不活跃
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }
}
