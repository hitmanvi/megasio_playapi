<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'icon',
        'name',
        'display_name',
        'currency',
        'type',
        'amounts',
        'max_amount',
        'min_amount',
        'enabled',
        'sort_id',
        'synced_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amounts' => 'array',
        'enabled' => 'boolean',
        'sort_id' => 'integer',
        'max_amount' => 'decimal:8',
        'min_amount' => 'decimal:8',
        'synced_at' => 'datetime',
    ];

    /**
     * Payment method type constants.
     */
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAW = 'withdraw';

    /**
     * Scope to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by currency.
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope to filter enabled payment methods.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to filter disabled payment methods.
     */
    public function scopeDisabled($query)
    {
        return $query->where('enabled', false);
    }

    /**
     * Scope to filter deposit methods.
     */
    public function scopeDeposit($query)
    {
        return $query->where('type', self::TYPE_DEPOSIT);
    }

    /**
     * Scope to filter withdraw methods.
     */
    public function scopeWithdraw($query)
    {
        return $query->where('type', self::TYPE_WITHDRAW);
    }

    /**
     * Check if the payment method is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Scope to order by sort_id.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_id', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Check if the amount is within the allowed range.
     */
    public function isAmountValid($amount): bool
    {
        // Check min amount
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }

        // Check max amount
        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }

        // Check if amount is in the fixed amounts array
        if ($this->amounts && is_array($this->amounts) && count($this->amounts) > 0) {
            return in_array((string)$amount, array_map('strval', $this->amounts));
        }

        return true;
    }
}
