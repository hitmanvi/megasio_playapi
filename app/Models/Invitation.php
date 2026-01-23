<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inviter_id',
        'invitee_id',
        'total_reward',
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
}
