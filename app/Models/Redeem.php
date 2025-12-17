<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redeem extends Model
{
    protected $fillable = [
        'user_id',
        'order_no',
        'out_trade_no',
        'sc_amount',
        'exchange_rate',
        'usd_amount',
        'actual_amount',
        'fee',
        'payment_method_id',
        'withdraw_info',
        'extra_info',
        'status',
        'pay_status',
        'approved',
        'user_ip',
        'completed_at',
        'note',
    ];

    protected $casts = [
        'sc_amount' => 'decimal:8',
        'exchange_rate' => 'decimal:8',
        'usd_amount' => 'decimal:8',
        'actual_amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'withdraw_info' => 'array',
        'extra_info' => 'array',
        'approved' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_REJECTED = 'REJECTED';

    // Pay status constants
    const PAY_STATUS_PENDING = 'PENDING';
    const PAY_STATUS_PAID = 'PAID';
    const PAY_STATUS_FAILED = 'FAILED';
    const PAY_STATUS_CANCELLED = 'CANCELLED';

    /**
     * Get the user that owns the redeem.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment method for this redeem.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPayStatus($query, string $payStatus)
    {
        return $query->where('pay_status', $payStatus);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPaid(): bool
    {
        return $this->pay_status === self::PAY_STATUS_PAID;
    }

    public function isApproved(): bool
    {
        return $this->approved === true;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}

