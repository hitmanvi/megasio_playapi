<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'currency',
        'amount',
        'type',
        'status',
        'related_entity_id',
        'notes',
        'transaction_time',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'transaction_time' => 'datetime',
    ];

    /**
     * Transaction types constants.
     */
    const TYPE_DEPOSIT = 'DEPOSIT';
    const TYPE_WITHDRAWAL = 'WITHDRAWAL';
    const TYPE_WITHDRAWAL_UNFREEZE = 'WITHDRAWAL_UNFREEZE';
    const TYPE_FEE = 'FEE';
    const TYPE_TRANSFER_IN = 'TRANSFER_IN';
    const TYPE_TRANSFER_OUT = 'TRANSFER_OUT';
    const TYPE_REFUND = 'REFUND';
    const TYPE_BET = 'BET';
    const TYPE_PAYOUT = 'PAYOUT';
    /**
     * Transaction status constants.
     */
    const STATUS_COMPLETED = 'COMPLETED';

    /**
     * Get the user that owns the transaction.
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
     * Scope to filter by currency.
     */
    public function scopeForCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_time', [$startDate, $endDate]);
    }


}
