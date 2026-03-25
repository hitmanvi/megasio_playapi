<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethodFieldConfig extends Model
{
    protected $fillable = [
        'name',
        'deposit_fields',
        'withdraw_fields',
    ];

    protected $casts = [
        'deposit_fields' => 'array',
        'withdraw_fields' => 'array',
    ];

    /**
     * 规范化字段列表：只保留含非空 key 的项，并统一为 unique（bool）
     * 兼容历史数据中的 readonly，与 unique 同义
     *
     * @param  array<int, mixed>  $fields
     * @return list<array{key: string, unique: bool}>
     */
    public static function normalizeFieldsArray(array $fields): array
    {
        $out = [];
        foreach ($fields as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }
            $flag = $item['unique'] ?? $item['readonly'] ?? false;
            $out[] = [
                'key' => $key,
                'unique' => filter_var($flag, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $out;
    }

    /**
     * extra_info 里出现、但当前配置里还没有声明的 key，追加到 deposit_fields / withdraw_fields（unique 默认 false）
     *
     * @param  \App\Models\UserPaymentExtraInfo::TYPE_*  $paymentType  deposit|withdraw
     */
    public static function appendMissingKeysFromExtraInfo(string $name, string $paymentType, array $extraInfo): void
    {
        $keys = self::keysFromExtraInfoPayload($extraInfo);
        if ($keys === []) {
            return;
        }

        $column = $paymentType === UserPaymentExtraInfo::TYPE_WITHDRAW ? 'withdraw_fields' : 'deposit_fields';

        $config = static::firstOrNew(['name' => $name]);
        $fields = $config->{$column};
        if (!is_array($fields)) {
            $fields = [];
        }

        $existingKeys = [];
        foreach (static::normalizeFieldsArray($fields) as $f) {
            $existingKeys[$f['key']] = true;
        }

        $changed = false;
        foreach ($keys as $key) {
            if (isset($existingKeys[$key])) {
                continue;
            }
            $fields[] = ['key' => $key, 'unique' => false];
            $existingKeys[$key] = true;
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $config->name = $name;
        $config->{$column} = static::normalizeFieldsArray($fields);
        $config->save();
    }

    /**
     * @return list<string>
     */
    protected static function keysFromExtraInfoPayload(array $extraInfo): array
    {
        $keys = [];
        foreach ($extraInfo as $key => $_) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (UserPaymentExtraInfo::isSensitivePaymentFieldKey($key)) {
                continue;
            }
            $keys[] = $key;
        }

        return array_values(array_unique($keys));
    }
}
