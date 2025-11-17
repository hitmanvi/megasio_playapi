<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserStatistic extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'total_deposit',
        'total_withdraw',
        'total_order_amount',
        'total_payout',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_deposit' => 'decimal:8',
        'total_withdraw' => 'decimal:8',
        'total_order_amount' => 'decimal:8',
        'total_payout' => 'decimal:8',
    ];

    /**
     * Get the user that owns the statistics.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the extended attributes for this statistic.
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(UserStatisticAttribute::class, 'user_id', 'user_id');
    }

    /**
     * Increment deposit total.
     */
    public function incrementDeposit(float $amount): void
    {
        $this->increment('total_deposit', $amount);
    }

    /**
     * Increment withdraw total.
     */
    public function incrementWithdraw(float $amount): void
    {
        $this->increment('total_withdraw', $amount);
    }

    /**
     * Increment order amount total.
     */
    public function incrementOrderAmount(float $amount): void
    {
        $this->increment('total_order_amount', $amount);
    }

    /**
     * Increment payout total.
     */
    public function incrementPayout(float $amount): void
    {
        $this->increment('total_payout', $amount);
    }
}
