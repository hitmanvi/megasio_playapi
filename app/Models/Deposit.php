<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'order_no',
        'out_trade_no',
        'currency',
        'amount',
        'actual_amount',
        'payment_method_id',
        'deposit_info',
        'extra_info',
        'status',
        'pay_status',
        'pay_fee',
        'user_ip',
        'expired_at',
        'finished_at',
        'is_disputed',
        'resolved_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'actual_amount' => 'decimal:8',
        'pay_fee' => 'decimal:8',
        'deposit_info' => 'array',
        'extra_info' => 'array',
        'expired_at' => 'datetime',
        'finished_at' => 'datetime',
        'is_disputed' => 'boolean',
    ];

    /**
     * Deposit status constants.
     */
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_EXPIRED = 'EXPIRED';

    /**
     * Pay status constants.
     */
    const PAY_STATUS_PENDING = 'PENDING';
    const PAY_STATUS_PAID = 'PAID';
    const PAY_STATUS_FAILED = 'FAILED';
    const PAY_STATUS_CANCELLED = 'CANCELLED';

    /**
     * Get the user that owns the deposit.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment method for this deposit.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
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
     * Scope to filter by pay status.
     */
    public function scopeByPayStatus($query, $payStatus)
    {
        return $query->where('pay_status', $payStatus);
    }

    /**
     * Scope to filter by currency.
     */
    public function scopeForCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope to filter by payment method.
     */
    public function scopeForPaymentMethod($query, $paymentMethodId)
    {
        return $query->where('payment_method_id', $paymentMethodId);
    }

    /**
     * Scope to filter pending deposits.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter completed deposits.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to filter failed deposits.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to filter expired deposits.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED)
            ->orWhere(function ($q) {
                $q->where('expired_at', '<=', now())
                    ->where('status', '!=', self::STATUS_COMPLETED)
                    ->where('status', '!=', self::STATUS_FAILED)
                    ->where('status', '!=', self::STATUS_CANCELLED);
            });
    }

    /**
     * Scope to filter paid deposits.
     */
    public function scopePaid($query)
    {
        return $query->where('pay_status', self::PAY_STATUS_PAID);
    }

    /**
     * Check if the deposit is expired.
     */
    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast() 
            && !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if the deposit is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the deposit is paid.
     */
    public function isPaid(): bool
    {
        return $this->pay_status === self::PAY_STATUS_PAID;
    }
}

