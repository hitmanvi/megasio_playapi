<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderArchive extends Model
{
    protected $table = 'order_archives';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'game_id',
        'brand_id',
        'amount',
        'payout',
        'status',
        'currency',
        'payment_currency',
        'payment_amount',
        'payment_payout',
        'notes',
        'finished_at',
        'order_id',
        'out_id',
        'version',
        'archived_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'payout' => 'decimal:8',
        'payment_amount' => 'decimal:8',
        'payment_payout' => 'decimal:8',
        'finished_at' => 'datetime',
        'archived_at' => 'datetime',
        'version' => 'integer',
    ];

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the game for this order.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the brand for this order.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}

