<?php

namespace App\Models;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
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
     * 下单校验：已存 data 中 read_only 的字段，请求 extra_info 必须与已存 value 一致，否则抛错
     *
     * @param  self::TYPE_*  $type
     * @param  array<string, mixed>  $requestExtraInfo
     *
     * @throws Exception
     */
    public static function assertReadOnlyExtraInfoMatchesSaved(int $userId, string $paymentMethodName, string $type, array $requestExtraInfo): void
    {
        $record = static::where('user_id', $userId)
            ->where('name', $paymentMethodName)
            ->where('type', $type)
            ->first();

        $saved = is_array($record?->data) ? $record->data : [];
        if ($saved === []) {
            return;
        }

        $requestNorm = self::normalizeExtraInfoPayload($requestExtraInfo);

        foreach ($saved as $key => $entry) {
            if (!is_string($key) || $key === '' || self::isSensitivePaymentFieldKey($key)) {
                continue;
            }
            if (!is_array($entry) || !filter_var($entry['read_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $expected = trim((string) ($entry['value'] ?? ''));
            $reqEntry = $requestNorm[$key] ?? null;
            $actual = $reqEntry !== null
                ? trim((string) ($reqEntry['value'] ?? ''))
                : '';

            if ($expected !== $actual) {
                throw new Exception(
                    ErrorCode::VALIDATION_ERROR,
                    sprintf('extra_info.%s must match saved read-only value', $key)
                );
            }
        }
    }

    /**
     * 回调成功等场景：将该用户该支付方式下对应类型的 data 中全部字段标为 read_only
     *
     * @param  self::TYPE_*  $type
     */
    public static function markAllReadOnlyForUser(int $userId, string $paymentMethodName, string $type): void
    {
        $record = static::where('user_id', $userId)
            ->where('name', $paymentMethodName)
            ->where('type', $type)
            ->first();

        if (!$record || !is_array($record->data) || $record->data === []) {
            return;
        }

        $data = $record->data;
        foreach ($data as $key => $entry) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (self::isSensitivePaymentFieldKey($key)) {
                continue;
            }

            if (is_array($entry)) {
                $data[$key] = [
                    'value' => (string) ($entry['value'] ?? ''),
                    'read_only' => true,
                ];
            } else {
                $data[$key] = [
                    'value' => is_scalar($entry) ? (string) $entry : '',
                    'read_only' => true,
                ];
            }
        }

        $record->data = $data;
        $record->save();
    }

    /**
     * 充值成功后：同 name 下提现侧与充值侧对齐并全部只读；无提现记录则创建
     * 需在充值侧已 markAllReadOnlyForUser(TYPE_DEPOSIT) 之后调用
     */
    public static function syncWithdrawReadOnlyAfterDepositSuccess(int $userId, string $paymentMethodName): void
    {
        self::mirrorOppositeReadOnly($userId, $paymentMethodName, self::TYPE_DEPOSIT, self::TYPE_WITHDRAW);
    }

    /**
     * 提现成功后：同 name 下充值侧与提现侧对齐并全部只读；无充值记录则创建
     * 需在提现侧已 markAllReadOnlyForUser(TYPE_WITHDRAW) 之后调用
     */
    public static function syncDepositReadOnlyAfterWithdrawSuccess(int $userId, string $paymentMethodName): void
    {
        self::mirrorOppositeReadOnly($userId, $paymentMethodName, self::TYPE_WITHDRAW, self::TYPE_DEPOSIT);
    }

    /**
     * 将 sourceType 的 data 合并进 targetType 记录，并将 target 侧全部字段标为 read_only（无 target 行则创建）
     *
     * @param  self::TYPE_DEPOSIT|self::TYPE_WITHDRAW  $sourceType
     * @param  self::TYPE_DEPOSIT|self::TYPE_WITHDRAW  $targetType
     */
    protected static function mirrorOppositeReadOnly(int $userId, string $paymentMethodName, string $sourceType, string $targetType): void
    {
        $sourceRow = static::where('user_id', $userId)
            ->where('name', $paymentMethodName)
            ->where('type', $sourceType)
            ->first();

        $sourceData = is_array($sourceRow?->data) ? $sourceRow->data : [];

        $targetRow = static::firstOrNew([
            'user_id' => $userId,
            'name' => $paymentMethodName,
            'type' => $targetType,
        ]);

        $tData = is_array($targetRow->data) ? $targetRow->data : [];

        foreach ($sourceData as $key => $entry) {
            if (!is_string($key) || $key === '' || self::isSensitivePaymentFieldKey($key)) {
                continue;
            }
            $val = is_array($entry)
                ? (string) ($entry['value'] ?? '')
                : (is_scalar($entry) ? (string) $entry : '');
            $tData[$key] = [
                'value' => $val,
                'read_only' => true,
            ];
        }

        $cleaned = [];
        foreach ($tData as $key => $entry) {
            if (!is_string($key) || $key === '' || self::isSensitivePaymentFieldKey($key)) {
                continue;
            }
            if (is_array($entry)) {
                $cleaned[$key] = [
                    'value' => (string) ($entry['value'] ?? ''),
                    'read_only' => true,
                ];
            } else {
                $cleaned[$key] = [
                    'value' => is_scalar($entry) ? (string) $entry : '',
                    'read_only' => true,
                ];
            }
        }

        $targetRow->data = $cleaned;
        $targetRow->save();
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
