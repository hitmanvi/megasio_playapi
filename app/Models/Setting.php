<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /**
     * 缓存时间（秒）
     */
    const CACHE_TTL = 3600;

    /**
     * 缓存 key 前缀
     */
    const CACHE_PREFIX = 'setting:';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * 获取设置值
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * 设置值
     */
    public static function setValue(string $key, mixed $value, string $type = 'string', string $group = 'general', ?string $description = null): self
    {
        $storedValue = self::prepareValue($value, $type);

        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]
        );

        // 清除缓存
        Cache::forget(self::CACHE_PREFIX . $key);

        return $setting;
    }

    /**
     * 获取某个分组的所有设置
     */
    public static function getGroup(string $group): array
    {
        $settings = self::where('group', $group)->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = self::castValue($setting->value, $setting->type);
        }

        return $result;
    }

    /**
     * 获取所有设置（按分组）
     */
    public static function getAllGrouped(): array
    {
        $settings = self::all();

        $result = [];
        foreach ($settings as $setting) {
            if (!isset($result[$setting->group])) {
                $result[$setting->group] = [];
            }
            $result[$setting->group][$setting->key] = self::castValue($setting->value, $setting->type);
        }

        return $result;
    }

    /**
     * 清除所有设置缓存
     */
    public static function clearCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget(self::CACHE_PREFIX . $setting->key);
        }
    }

    /**
     * 根据类型转换值
     */
    protected static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer', 'int' => (int) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($value, true) ?? [],
            'float', 'double' => (float) $value,
            default => $value,
        };
    }

    /**
     * 准备存储的值
     */
    protected static function prepareValue(mixed $value, string $type): string
    {
        if (in_array($type, ['json', 'array'])) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}

