<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundlePurchase extends Model
{
    // 状态常量
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    // 支付状态常量
    const PAY_STATUS_UNPAID = 'unpaid';
    const PAY_STATUS_PAID = 'paid';
    const PAY_STATUS_REFUNDED = 'refunded';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_no',
        'user_id',
        'bundle_id',
        'payment_method_id',
        'gold_coin',
        'social_coin',
        'amount',
        'currency',
        'out_trade_no',
        'status',
        'pay_status',
        'user_ip',
        'payment_info',
        'notes',
        'paid_at',
        'finished_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'gold_coin' => 'decimal:8',
        'social_coin' => 'decimal:8',
        'amount' => 'decimal:8',
        'payment_info' => 'array',
        'paid_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联Bundle
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    /**
     * 关联支付方式
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Scope: 根据用户筛选
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: 已完成的
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: 待处理的
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: 已支付的
     */
    public function scopePaid($query)
    {
        return $query->where('pay_status', self::PAY_STATUS_PAID);
    }

    /**
     * 是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 是否已支付
     */
    public function isPaid(): bool
    {
        return $this->pay_status === self::PAY_STATUS_PAID;
    }

    /**
     * 是否待处理
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 生成订单号
     */
    public static function generateOrderNo(): string
    {
        return 'BP' . date('YmdHis') . strtoupper(substr(uniqid(), -6));
    }

    /**
     * 格式化输出
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'bundle' => $this->bundle ? $this->bundle->toApiArray() : null,
            'payment_method' => $this->paymentMethod ? [
                'id' => $this->paymentMethod->id,
                'key' => $this->paymentMethod->key,
                'name' => $this->paymentMethod->name,
                'display_name' => $this->paymentMethod->display_name,
            ] : null,
            'gold_coin' => (float) $this->gold_coin,
            'social_coin' => (float) $this->social_coin,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'pay_status' => $this->pay_status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

