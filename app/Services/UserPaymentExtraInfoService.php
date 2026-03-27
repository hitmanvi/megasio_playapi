<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodFieldConfig;
use App\Models\UserPaymentExtraInfo;

class UserPaymentExtraInfoService
{
    /**
     * 将充提订单的 extra_info 合并到用户该支付方式下的 data（按 payment_methods.name + 充/提类型）
     *
     * @param  UserPaymentExtraInfo::TYPE_*  $type
     * @param  array<string, mixed>  $extraInfo
     */
    public function mergeFromExtraInfo(int $userId, string $paymentMethodName, array $extraInfo, string $type): void
    {
        if ($extraInfo === []) {
            return;
        }

        $incoming = self::normalizeExtraInfoPayload($extraInfo);

        if ($incoming === []) {
            return;
        }

        $record = UserPaymentExtraInfo::firstOrNew([
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
     * 根据 PaymentMethodFieldConfig 中的 unique 字段，检查本次 extra_info 的值是否与其他用户已存值相同；
     * 若至少两名用户该字段值相同，则为相关行的该字段标记 {@see UserPaymentExtraInfo::DATA_KEY_VALUE_DUPLICATE_ACROSS_USERS}，
     * 并将行级 duplicate_across_user 置为 true
     *
     * 应在 mergeFromExtraInfo 之后调用（通常由 {@see \App\Jobs\MarkPaymentExtraInfoDuplicateUniqueValuesJob} 异步执行）
     *
     * @param  UserPaymentExtraInfo::TYPE_*  $type
     * @param  array<string, mixed>  $extraInfo
     */
    public function markDuplicateUniqueValuesAcrossUsers(
        string $paymentMethodName,
        string $type,
        array $extraInfo
    ): void {
        $uniqueKeys = PaymentMethodFieldConfig::uniqueFieldKeysFor($paymentMethodName, $type);
        if ($uniqueKeys === []) {
            return;
        }

        $norm = self::normalizeExtraInfoPayload($extraInfo);
        $allRows = UserPaymentExtraInfo::where('name', $paymentMethodName)
            ->where('type', $type)
            ->get();

        /** @var array<int, array<string, true>> */
        $rowIdToKeys = [];

        foreach ($uniqueKeys as $key) {
            if (self::isSensitivePaymentFieldKey($key)) {
                continue;
            }

            $reqEntry = $norm[$key] ?? null;
            if ($reqEntry === null) {
                continue;
            }

            $needle = trim((string) ($reqEntry['value'] ?? ''));
            if ($needle === '') {
                continue;
            }

            $matchingRowIds = [];
            foreach ($allRows as $row) {
                if ($this->trimmedDataValueForKey($row->data, $key) === $needle) {
                    $matchingRowIds[] = $row->id;
                }
            }

            if (count($matchingRowIds) < 2) {
                continue;
            }

            foreach ($matchingRowIds as $rid) {
                if (!isset($rowIdToKeys[$rid])) {
                    $rowIdToKeys[$rid] = [];
                }
                $rowIdToKeys[$rid][$key] = true;
            }
        }

        foreach ($rowIdToKeys as $rowId => $keyMap) {
            $row = UserPaymentExtraInfo::find($rowId);
            if (!$row) {
                continue;
            }

            $data = is_array($row->data) ? $row->data : [];
            foreach (array_keys($keyMap) as $key) {
                if (!isset($data[$key])) {
                    continue;
                }
                if (!is_array($data[$key])) {
                    $data[$key] = [
                        'value' => is_scalar($data[$key]) ? (string) $data[$key] : '',
                        'read_only' => false,
                    ];
                }
                $data[$key][UserPaymentExtraInfo::DATA_KEY_VALUE_DUPLICATE_ACROSS_USERS] = true;
            }

            $row->data = $data;
            $row->duplicate_across_user = true;
            $row->save();
        }
    }

    /**
     * 下单校验：已存 data 中 read_only 的字段，请求 extra_info 必须与已存 value 一致，否则抛错
     *
     * @param  UserPaymentExtraInfo::TYPE_*  $type
     * @param  array<string, mixed>  $requestExtraInfo
     *
     * @throws Exception
     */
    public function assertReadOnlyExtraInfoMatchesSaved(
        int $userId,
        string $paymentMethodName,
        string $type,
        array $requestExtraInfo
    ): void {
        $record = UserPaymentExtraInfo::where('user_id', $userId)
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
     * @param  UserPaymentExtraInfo::TYPE_*  $type
     */
    public function markAllReadOnlyForUser(int $userId, string $paymentMethodName, string $type): void
    {
        $record = UserPaymentExtraInfo::where('user_id', $userId)
            ->where('name', $paymentMethodName)
            ->where('type', $type)
            ->first();

        if (!$record || !is_array($record->data) || $record->data === []) {
            return;
        }

        $data = $record->data;
        $dupKey = UserPaymentExtraInfo::DATA_KEY_VALUE_DUPLICATE_ACROSS_USERS;

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
                if (array_key_exists($dupKey, $entry)) {
                    $data[$key][$dupKey] = filter_var($entry[$dupKey], FILTER_VALIDATE_BOOLEAN);
                }
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
     */
    public function syncWithdrawReadOnlyAfterDepositSuccess(int $userId, string $paymentMethodName): void
    {
        $this->mirrorOppositeReadOnly(
            $userId,
            $paymentMethodName,
            UserPaymentExtraInfo::TYPE_DEPOSIT,
            UserPaymentExtraInfo::TYPE_WITHDRAW
        );
    }

    /**
     * 提现成功后：同 name 下充值侧与提现侧对齐并全部只读；无充值记录则创建
     */
    public function syncDepositReadOnlyAfterWithdrawSuccess(int $userId, string $paymentMethodName): void
    {
        $this->mirrorOppositeReadOnly(
            $userId,
            $paymentMethodName,
            UserPaymentExtraInfo::TYPE_WITHDRAW,
            UserPaymentExtraInfo::TYPE_DEPOSIT
        );
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

    /**
     * 将 sourceType 的 data 合并进 targetType 记录，并将 target 侧全部字段标为 read_only（无 target 行则创建）
     *
     * @param  UserPaymentExtraInfo::TYPE_DEPOSIT|UserPaymentExtraInfo::TYPE_WITHDRAW  $sourceType
     * @param  UserPaymentExtraInfo::TYPE_DEPOSIT|UserPaymentExtraInfo::TYPE_WITHDRAW  $targetType
     */
    protected function mirrorOppositeReadOnly(
        int $userId,
        string $paymentMethodName,
        string $sourceType,
        string $targetType
    ): void {
        $dupKey = UserPaymentExtraInfo::DATA_KEY_VALUE_DUPLICATE_ACROSS_USERS;

        $sourceRow = UserPaymentExtraInfo::where('user_id', $userId)
            ->where('name', $paymentMethodName)
            ->where('type', $sourceType)
            ->first();

        $sourceData = is_array($sourceRow?->data) ? $sourceRow->data : [];

        $targetRow = UserPaymentExtraInfo::firstOrNew([
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
            if (is_array($entry) && array_key_exists($dupKey, $entry)) {
                $tData[$key][$dupKey] = filter_var($entry[$dupKey], FILTER_VALIDATE_BOOLEAN);
            }
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
                if (array_key_exists($dupKey, $entry)) {
                    $cleaned[$key][$dupKey] = filter_var($entry[$dupKey], FILTER_VALIDATE_BOOLEAN);
                }
            } else {
                $cleaned[$key] = [
                    'value' => is_scalar($entry) ? (string) $entry : '',
                    'read_only' => true,
                ];
            }
        }

        $targetRow->data = $cleaned;
        $targetRow->duplicate_across_user = (bool) ($targetRow->duplicate_across_user ?? false)
            || (bool) ($sourceRow?->duplicate_across_user ?? false);
        $targetRow->save();
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function trimmedDataValueForKey(?array $data, string $key): string
    {
        if ($data === null || !isset($data[$key])) {
            return '';
        }

        $entry = $data[$key];
        if (is_array($entry)) {
            return trim((string) ($entry['value'] ?? ''));
        }

        return is_scalar($entry) ? trim((string) $entry) : '';
    }

    /**
     * 当前用户在某支付方式、某类型下已保存的 extra 数据（按 payment_methods.name + type 关联）
     *
     * @param  UserPaymentExtraInfo::TYPE_DEPOSIT|UserPaymentExtraInfo::TYPE_WITHDRAW  $type
     * @return array{
     *     payment_method_id: int,
     *     name: string,
     *     type: string,
     *     data: array<string, mixed>|null
     * }
     */
    public function getReadonlySavedExtraInfoForPaymentMethod(int $userId, PaymentMethod $paymentMethod, string $type): array
    {
        if ($type !== UserPaymentExtraInfo::TYPE_DEPOSIT && $type !== UserPaymentExtraInfo::TYPE_WITHDRAW) {
            throw new \InvalidArgumentException('type must be deposit or withdraw');
        }

        $name = $paymentMethod->name;

        $row = UserPaymentExtraInfo::query()
            ->where('user_id', $userId)
            ->where('name', $name)
            ->where('type', $type)
            ->first();

        $data = null;
        if ($row) {
            $filtered = $this->filterSensitiveFromStoredData(is_array($row->data) ? $row->data : []);
            $data = $filtered === [] ? null : $filtered;
        }

        return [
            'payment_method_id' => $paymentMethod->id,
            'name' => $name,
            'type' => $type,
            'data' => $data,
        ];
    }

    /**
     * 用户扩展信息接口：去掉敏感 key、去掉各字段内的 value_duplicate_across_users
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterSensitiveFromStoredData(array $data): array
    {
        $dupKey = UserPaymentExtraInfo::DATA_KEY_VALUE_DUPLICATE_ACROSS_USERS;
        $out = [];
        foreach ($data as $key => $entry) {
            if (!is_string($key) || $key === '' || self::isSensitivePaymentFieldKey($key)) {
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }

            $clean = $entry;
            unset($clean[$dupKey]);
            $out[$key] = $clean;
        }

        return $out;
    }
}
