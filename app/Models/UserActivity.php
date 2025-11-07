<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'activity_type',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Activity type constants.
     */
    const TYPE_LOGIN = 'LOGIN';
    const TYPE_LOGOUT = 'LOGOUT';
    const TYPE_DEPOSIT = 'DEPOSIT';
    const TYPE_WITHDRAW = 'WITHDRAW';
    const TYPE_GAME_PLAY = 'GAME_PLAY';
    const TYPE_GAME_WIN = 'GAME_WIN';
    const TYPE_GAME_LOSE = 'GAME_LOSE';
    const TYPE_PROFILE_UPDATE = 'PROFILE_UPDATE';
    const TYPE_PASSWORD_CHANGE = 'PASSWORD_CHANGE';
    const TYPE_OTHER = 'OTHER';

    /**
     * Get the user that owns the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by activity type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
