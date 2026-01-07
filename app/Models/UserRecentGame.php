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
        'play_count',
        'max_multiplier',
    ];

    protected $casts = [
        'last_played_at' => 'datetime',
        'play_count' => 'integer',
        'max_multiplier' => 'decimal:2',
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
     * 
     * @param int $userId 用户ID
     * @param int $gameId 游戏ID
     * @param float $multiplier 本次奖励倍数
     */
    public static function recordPlay(int $userId, int $gameId, float $multiplier = 0): self
    {
        $record = self::firstOrNew([
            'user_id' => $userId,
            'game_id' => $gameId,
        ]);

        $record->last_played_at = now();
        $record->play_count = ($record->play_count ?? 0) + 1;
        
        // 更新最大倍数
        if ($multiplier > ($record->max_multiplier ?? 0)) {
            $record->max_multiplier = $multiplier;
        }
        
        $record->save();
        
        return $record;
    }
}
