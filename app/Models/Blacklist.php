<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'value',
        'reason',
        'hit_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hit_count' => 'integer',
    ];

    /**
     * 检查值是否在黑名单中
     */
    public static function isBlacklisted(string $value): bool
    {
        return static::where('value', $value)->exists();
    }

    /**
     * 检查并增加命中次数
     */
    public static function checkAndIncrement(string $value): bool
    {
        $blacklist = static::where('value', $value)->first();

        if ($blacklist) {
            $blacklist->increment('hit_count');
            return true;
        }

        return false;
    }

    /**
     * 添加到黑名单
     */
    public static function add(string $value, ?string $reason = null): self
    {
        return static::updateOrCreate(
            ['value' => $value],
            ['reason' => $reason]
        );
    }

    /**
     * 从黑名单移除
     */
    public static function remove(string $value): bool
    {
        return static::where('value', $value)->delete() > 0;
    }
}
