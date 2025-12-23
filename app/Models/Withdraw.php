<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdraw extends Model
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
        'withdraw_info',
        'extra_info',
        'status',
        'pay_status',
        'fee',
        'approved',
        'user_ip',
        'completed_at',
        'note',
        'last_callback_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'actual_amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'withdraw_info' => 'array',
        'extra_info' => 'array',
        'approved' => 'boolean',
        'completed_at' => 'datetime',
        'last_callback_at' => 'datetime',
    ];

    /**
     * Withdraw status constants.
     */
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Pay status constants.
     */
    const PAY_STATUS_PENDING = 'PENDING';
    const PAY_STATUS_PAID = 'PAID';
    const PAY_STATUS_FAILED = 'FAILED';
    const PAY_STATUS_CANCELLED = 'CANCELLED';

    /**
     * Get the user that owns the withdraw.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment method for this withdraw.
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
     * Scope to filter pending withdraws.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter completed withdraws.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to filter failed withdraws.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to filter rejected withdraws.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to filter paid withdraws.
     */
    public function scopePaid($query)
    {
        return $query->where('pay_status', self::PAY_STATUS_PAID);
    }

    /**
     * Scope to filter by approved status.
     */
    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    /**
     * Check if the withdraw is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the withdraw is paid.
     */
    public function isPaid(): bool
    {
        return $this->pay_status === self::PAY_STATUS_PAID;
    }

    /**
     * Check if the withdraw is approved.
     */
    public function isApproved(): bool
    {
        return $this->approved === true;
    }

    /**
     * Check if the withdraw is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}

