<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WeeklyCashback extends Model
{
    protected static function booted(): void
    {
        static::creating(function (WeeklyCashback $model) {
            if (empty($model->no)) {
                $model->no = Str::ulid()->toString();
            }
        });
    }
    /** 进行中 */
    const STATUS_ACTIVE = 'active';

    /** 待领取 */
    const STATUS_CLAIMABLE = 'claimable';

    /** 已领取 */
    const STATUS_CLAIMED = 'claimed';

    /** 已过期 */
    const STATUS_EXPIRED = 'expired';

    protected $table = 'weekly_cashbacks';

    protected $fillable = [
        'no',
        'user_id',
        'period',
        'currency',
        'wager',
        'payout',
        'status',
        'rate',
        'amount',
        'claimed_at',
    ];

    protected $casts = [
        'period' => 'integer',
        'wager' => 'decimal:8',
        'payout' => 'decimal:8',
        'rate' => 'decimal:4',
        'amount' => 'decimal:8',
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
