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
        'balance',
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
        'balance' => 'decimal:8',
        'transaction_time' => 'datetime',
    ];

    /**
     * Transaction types constants.
     */
    const TYPE_DEPOSIT = 'DEPOSIT';
    const TYPE_WITHDRAWAL = 'WITHDRAWAL';
    const TYPE_WITHDRAWAL_UNFREEZE = 'WITHDRAWAL_UNFREEZE';
    const TYPE_REFUND = 'REFUND';
    const TYPE_BET = 'BET';
    const TYPE_PAYOUT = 'PAYOUT';
    const TYPE_CHECK_IN_REWARD = 'CHECK_IN_REWARD';
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

    /**
     * 获取关联实体
     */
    public function getRelatedEntity(): ?array
    {
        if (!$this->related_entity_id) {
            return null;
        }

        switch ($this->type) {
            case self::TYPE_DEPOSIT:
                // 支持格式: "123" 或 "123_suffix"
                $depositId = explode('_', $this->related_entity_id)[0];
                $deposit = Deposit::find($depositId);
                if ($deposit) {
                    return [
                        'type' => 'deposit',
                        'data' => [
                            'id' => $deposit->id,
                            'order_no' => $deposit->order_no,
                            'amount' => (float) $deposit->amount,
                            'currency' => $deposit->currency,
                            'status' => $deposit->status,
                            'created_at' => $deposit->created_at?->format('Y-m-d H:i:s'),
                        ],
                    ];
                }
                break;

            case self::TYPE_WITHDRAWAL:
            case self::TYPE_WITHDRAWAL_UNFREEZE:
                // 支持格式: "123" 或 "123_suffix"
                $withdrawId = explode('_', $this->related_entity_id)[0];
                $withdraw = Withdraw::find($withdrawId);
                if ($withdraw) {
                    return [
                        'type' => 'withdraw',
                        'data' => [
                            'id' => $withdraw->id,
                            'order_no' => $withdraw->order_no,
                            'amount' => (float) $withdraw->amount,
                            'currency' => $withdraw->currency,
                            'status' => $withdraw->status,
                            'created_at' => $withdraw->created_at?->format('Y-m-d H:i:s'),
                        ],
                    ];
                }
                break;

            case self::TYPE_BET:
            case self::TYPE_PAYOUT:
            case self::TYPE_REFUND:
                // related_entity_id 格式: gameId_txid 或 gameId_txid_suffix
                $parts = explode('_', $this->related_entity_id, 2);
                if (count($parts) === 2) {
                    $gameId = $parts[0];
                    $txidPart = $parts[1];
                    
                    // txid 可能带有后缀 (txid_suffix)，尝试提取原始 txid
                    // 先尝试完整匹配，再尝试前缀匹配
                    $providerTx = ProviderTransaction::where('txid', $txidPart)->first();
                    if (!$providerTx) {
                        // 尝试用 LIKE 查询（针对测试数据带后缀的情况）
                        $providerTx = ProviderTransaction::where('txid', 'like', explode('_', $txidPart)[0] . '%')
                            ->where('game_id', $gameId)
                            ->first();
                    }

                    if ($providerTx && $providerTx->order_id) {
                        $order = Order::with('game')->find($providerTx->order_id);
                        if ($order) {
                            return [
                                'type' => 'order',
                                'data' => [
                                    'id' => $order->id,
                                    'order_id' => $order->order_id,
                                    'amount' => (float) $order->amount,
                                    'payout' => (float) $order->payout,
                                    'currency' => $order->currency,
                                    'status' => $order->status,
                                    'finished_at' => $order->finished_at?->format('Y-m-d H:i:s'),
                                    'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                                    'game' => $order->game ? [
                                        'id' => $order->game->id,
                                        'name' => $order->game->name,
                                        'thumbnail' => $order->game->thumbnail,
                                    ] : null,
                                ],
                            ];
                        }
                    }

                    // 找不到返回 null
                    return null;
                }
                break;
        }

        return null;
    }
}
