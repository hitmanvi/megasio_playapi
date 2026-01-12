<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCheckIn extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'check_in_date',
        'consecutive_days',
        'reward_day',
        'rewards_granted',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'consecutive_days' => 'integer',
        'reward_day' => 'integer',
        'rewards_granted' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
