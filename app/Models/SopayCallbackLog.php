<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SopayCallbackLog extends Model
{
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'out_trade_no',
        'subject',
        'status',
        'amount',
        'request_headers',
        'request_body',
        'sign_data',
        'signature',
        'signature_valid',
        'process_result',
        'process_error',
        'ip',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'request_headers' => 'array',
        'request_body' => 'array',
        'signature_valid' => 'boolean',
    ];

    /**
     * 记录回调日志
     */
    public static function log(array $data): self
    {
        return static::create($data);
    }
}

