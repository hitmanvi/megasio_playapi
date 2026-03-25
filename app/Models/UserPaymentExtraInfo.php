<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPaymentExtraInfo extends Model
{
    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_WITHDRAW = 'withdraw';

    protected $table = 'user_payment_extra_infos';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 将充提订单的 extra_info 合并到用户该支付方式下的 data（按 payment_methods.name + 充/提类型）
     *
     * @param  self::TYPE_*  $type  deposit 或 withdraw
     * @param  array<string, mixed>  $extraInfo  请求中的 extra_info（key => 标量 或 含 value/read_only 的数组）
     */
    public static function mergeFromExtraInfo(int $userId, string $paymentMethodName, array $extraInfo, string $type): void
    {
        if ($extraInfo === []) {
            return;
        }

        $incoming = self::normalizeExtraInfoPayload($extraInfo);

        if ($incoming === []) {
            return;
        }

        $record = static::firstOrNew([
            'user_id' => $userId,
            'name' => $paymentMethodName,
            'type' => $type,
        ]);

        $existing = is_array($record->data) ? $record->data : [];
        $existing = array_filter(
            $existing,
            static fn ($_, $k) => !self::isSensitivePaymentFieldKey((string) $k),
            ARRAY_FILTER_USE_BOTH
        );

        foreach ($incoming as $key => $entry) {
            if (isset($existing[$key]['read_only']) && $existing[$key]['read_only'] === true) {
                continue;
            }
            $existing[$key] = $entry;
        }

        $record->data = $existing;
        $record->save();
    }

    /**
     * 不落库的敏感字段（卡安全码等）
     */
    public static function isSensitivePaymentFieldKey(string $key): bool
    {
        $k = strtolower(trim($key));
        if ($k === '') {
            return false;
        }

        $exact = [
            'cvv', 'cvc', 'cvv2', 'cvc2', 'cid', 'cav',
            'card_cvv', 'card_cvc', 'card_cvv2', 'card_cvc2',
            'security_code', 'card_security_code',
            'verification_value', 'card_verification_value',
        ];
        if (in_array($k, $exact, true)) {
            return true;
        }

        if (str_ends_with($k, '_cvv') || str_ends_with($k, '_cvc')
            || str_ends_with($k, '_cvv2') || str_ends_with($k, '_cvc2')) {
            return true;
        }

        return (bool) preg_match('/(^|_)(cvv|cvc)(2)?(_|$)/', $k);
    }

    /**
     * @param  array<string, mixed>  $extraInfo
     * @return array<string, array{value: string, read_only: bool}>
     */
    public static function normalizeExtraInfoPayload(array $extraInfo): array
    {
        $out = [];

        foreach ($extraInfo as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (self::isSensitivePaymentFieldKey($key)) {
                continue;
            }

            if (is_array($value) && array_key_exists('value', $value)) {
                $v = $value['value'];
                $out[$key] = [
                    'value' => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE),
                    'read_only' => filter_var($value['read_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            } else {
                $out[$key] = [
                    'value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                    'read_only' => false,
                ];
            }
        }

        return $out;
    }
}
