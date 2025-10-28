<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
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
        'notes',
        'finished_at',
        'order_id',
        'out_id',
        'version',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'payout' => 'decimal:8',
        'finished_at' => 'datetime',
        'version' => 'integer',
    ];

    /**
     * Order status constants.
     */
    const STATUS_PENDING = 'PENDING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_SETTLED = 'SETTLED';

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

    /**
     * Scope to filter by brand.
     */
    public function scopeForBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope to filter by currency.
     */
    public function scopeForCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope to filter pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter completed orders.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to filter settled orders.
     */
    public function scopeSettled($query)
    {
        return $query->where('status', self::STATUS_SETTLED);
    }
}
