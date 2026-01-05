<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRecentGame extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'last_played_at',
    ];

    protected $casts = [
        'last_played_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联游戏
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * 记录用户游玩游戏
     */
    public static function recordPlay(int $userId, int $gameId): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'game_id' => $gameId],
            ['last_played_at' => now()]
        );
    }
}

