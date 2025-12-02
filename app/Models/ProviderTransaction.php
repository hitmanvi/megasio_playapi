<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderTransaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'game_id',
        'user_id',
        'order_id',
        'txid',
        'round_id',
        'detail',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'detail' => 'array',
    ];

    /**
     * Get the game that owns the transaction.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order that owns the transaction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter by game.
     */
    public function scopeByGame($query, int $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by order.
     */
    public function scopeByOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope to filter by round ID.
     */
    public function scopeByRoundId($query, string $roundId)
    {
        return $query->where('round_id', $roundId);
    }

    /**
     * Scope to filter by txid.
     */
    public function scopeByTxid($query, string $txid)
    {
        return $query->where('txid', $txid);
    }
}
