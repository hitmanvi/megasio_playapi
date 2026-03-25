<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPaymentExtraInfo extends Model
{
    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_WITHDRAW = 'withdraw';

    /** data[key] 内：该字段值与其他用户在同支付方式、同类型下重复（配置为 unique 的字段） */
    public const DATA_KEY_VALUE_DUPLICATE_ACROSS_USERS = 'value_duplicate_across_users';

    protected $table = 'user_payment_extra_infos';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
