<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    /**
     * 缓存时间（秒）
     */
    const CACHE_TTL = 60;

    /**
     * 缓存 key 前缀
     */
    const CACHE_PREFIX = 'setting:';

    /**
     * 获取设置值
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            return $this->castValue($setting->value, $setting->type);
        });
    }

    /**
     * 设置值
     *
     * @param string $key
     * @param mixed $value
     * @param string $type
     * @param string $group
     * @param string|null $description
     * @return Setting
     */
    public function setValue(string $key, mixed $value, string $type = 'string', string $group = 'general', ?string $description = null): Setting
    {
        $storedValue = $this->prepareValue($value, $type);

        $setting = Setting::updateOrCreate(
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
     *
     * @param string $group
     * @return array
     */
    public function getGroup(string $group, bool $filterEnabled = false): array
    {
        $settings = Setting::where('group', $group)->get();

        $result = [];
        foreach ($settings as $setting) {
            $value = $this->castValue($setting->value, $setting->type);
            
            // 如果启用过滤且配置项有 enabled 字段且为 false，则跳过
            if ($filterEnabled && is_array($value) && isset($value['enabled']) && !$value['enabled']) {
                continue;
            }
            
            $result[$setting->key] = $value;
        }

        return $result;
    }

    /**
     * 获取所有设置（按分组）
     *
     * @return array
     */
    public function getAllGrouped(): array
    {
        $settings = Setting::all();

        $result = [];
        foreach ($settings as $setting) {
            if (!isset($result[$setting->group])) {
                $result[$setting->group] = [];
            }
            $result[$setting->group][$setting->key] = $this->castValue($setting->value, $setting->type);
        }

        return $result;
    }

    /**
     * 清除所有设置缓存
     *
     * @return void
     */
    public function clearCache(): void
    {
        $settings = Setting::all();
        foreach ($settings as $setting) {
            Cache::forget(self::CACHE_PREFIX . $setting->key);
        }
    }

    /**
     * 根据类型转换值
     *
     * @param string|null $value
     * @param string $type
     * @return mixed
     */
    public function castValue(?string $value, string $type): mixed
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
     *
     * @param mixed $value
     * @param string $type
     * @return string
     */
    public function prepareValue(mixed $value, string $type): string
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
