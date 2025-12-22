<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTagLog extends Model
{
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tag_id',
        'value',
        'reason',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联标签
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    /**
     * 记录打标签日志
     */
    public static function log(int $userId, int $tagId, string $value, ?string $reason = null): self
    {
        return static::create([
            'user_id' => $userId,
            'tag_id' => $tagId,
            'value' => $value,
            'reason' => $reason,
        ]);
    }
}

