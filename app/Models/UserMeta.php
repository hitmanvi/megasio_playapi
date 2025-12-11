<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class UserMeta extends Model
{
    /**
     * 常用 key 常量
     */
    const KEY_LOGIN_IP = 'login_ip';               // 登录 IP
    const KEY_LOGIN_UA = 'login_ua';               // 登录 User-Agent
    const KEY_REGISTER_IP = 'register_ip';         // 注册 IP
    const KEY_REGISTER_UA = 'register_ua';         // 注册 User-Agent

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'user_id',
    ];

    /**
     * Get the user that owns the meta.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 添加用户的 meta 值
     */
    public static function addValue(int $userId, string $key, string $value): self
    {
        return self::create([
            'user_id' => $userId,
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * 获取用户某个 key 的最新一条记录
     */
    public static function getLatest(int $userId, string $key): ?string
    {
        return self::where('user_id', $userId)
            ->where('key', $key)
            ->latest()
            ->value('value');
    }

    /**
     * 获取用户某个 key 的所有记录
     */
    public static function getAll(int $userId, string $key): array
    {
        return self::where('user_id', $userId)
            ->where('key', $key)
            ->latest()
            ->pluck('value')
            ->toArray();
    }

    /**
     * 获取用户的所有 meta（按 key 分组）
     */
    public static function getAllForUser(int $userId): array
    {
        $metas = self::where('user_id', $userId)->latest()->get();
        
        $result = [];
        foreach ($metas as $meta) {
            if (!isset($result[$meta->key])) {
                $result[$meta->key] = [];
            }
            $result[$meta->key][] = $meta->value;
        }
        
        return $result;
    }

    /**
     * 根据 key 和 value 查找用户ID集合
     */
    public static function getUserIdsByValue(string $key, string $value): array
    {
        return self::where('key', $key)
            ->where('value', $value)
            ->distinct()
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * 根据 key 和 value 查找用户集合
     */
    public static function getUsersByValue(string $key, string $value): Collection
    {
        $userIds = self::getUserIdsByValue($key, $value);
        return User::whereIn('id', $userIds)->get();
    }

    /**
     * 根据 key 和 value 模糊查找用户ID集合
     */
    public static function getUserIdsByValueLike(string $key, string $value): array
    {
        return self::where('key', $key)
            ->where('value', 'like', "%{$value}%")
            ->distinct()
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * 根据 value 查找用户ID集合（不限 key）
     */
    public static function getUserIdsByValueAnyKey(string $value): array
    {
        return self::where('value', $value)
            ->distinct()
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * 根据 value 查找用户集合（不限 key）
     */
    public static function getUsersByValueAnyKey(string $value): Collection
    {
        $userIds = self::getUserIdsByValueAnyKey($value);
        return User::whereIn('id', $userIds)->get();
    }

    /**
     * 根据 value 模糊查找用户ID集合（不限 key）
     */
    public static function getUserIdsByValueLikeAnyKey(string $value): array
    {
        return self::where('value', 'like', "%{$value}%")
            ->distinct()
            ->pluck('user_id')
            ->toArray();
    }
}
