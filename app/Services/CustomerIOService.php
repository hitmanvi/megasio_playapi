<?php

namespace App\Services;

use App\Models\User;
use App\Models\VipLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CustomerIOService
{
    private const TRACK_API_CUSTOMERS = 'https://track.customer.io/api/v1/customers';

    // -------------------------------------------------------------------------
    // Inbound webhook（Reporting / Journey）
    // -------------------------------------------------------------------------

    /**
     * X-CIO-Signature + X-CIO-Timestamp：签名为 HMAC-SHA256 hex，原文为 v0:{timestamp}:{rawBody}。
     *
     * @see https://customer.io/docs/journeys/webhooks-action/#securely-verify-requests
     */
    public static function verifyWebhookSignature(string $rawBody, ?string $signatureHex, ?string $timestampHeader, string $secret): bool
    {
        if ($secret === '' || self::isBlank($signatureHex) || self::isBlank($timestampHeader)) {
            return false;
        }

        $timestamp = trim((string) $timestampHeader);
        $signature = strtolower(self::extractSignatureHex((string) $signatureHex));
        $signedPayload = 'v0:'.$timestamp.':'.$rawBody;
        $expectedHex = strtolower(hash_hmac('sha256', $signedPayload, $secret));

        return hash_equals($expectedHex, $signature);
    }

    /**
     * 按 config 校验 HTTP 头；通过返回 null，否则返回须直接输出的 Response（503 / 401）。
     */
    public static function ensureInboundWebhookSignature(Request $request): ?Response
    {
        if (! config('services.customer_io.webhook.verify_signature')) {
            Log::debug('Customer.io webhook signature verify: skipped (verify_signature disabled)');

            return null;
        }

        $raw = $request->getContent();
        $secret = (string) (config('services.customer_io.webhook.signing_secret') ?? '');
        $cioSig = self::firstHeader($request, ['X-CIO-Signature', 'X-Cio-Signature']);
        $cioTs = self::firstHeader($request, ['X-CIO-Timestamp', 'X-Cio-Timestamp']);

        Log::debug('Customer.io webhook signature verify: start', [
            'body_bytes' => strlen($raw),
            'x_cio_signature_present' => ! self::isBlank($cioSig),
            'x_cio_timestamp_present' => ! self::isBlank($cioTs),
            'signing_secret_configured' => $secret !== '',
        ]);

        if ($secret === '') {
            Log::error('Customer.io webhook verify_signature enabled but signing secret empty');

            return response('Webhook signing not configured', 503);
        }

        $ok = self::verifyWebhookSignature($raw, $cioSig, $cioTs, $secret);

        Log::debug('Customer.io webhook signature verify: result', ['valid' => $ok]);

        if (! $ok) {
            Log::warning('Customer.io webhook signature invalid', ['body_bytes' => strlen($raw)]);

            return response('Invalid signature', 401);
        }

        return null;
    }

    /**
     * 去掉 `sha256=` 等前缀，得到十六进制签名字符串。
     */
    private static function extractSignatureHex(string $signatureHeader): string
    {
        $sig = trim($signatureHeader);
        if (str_contains($sig, '=')) {
            $parts = explode('=', $sig, 2);
            $sig = trim(end($parts));
        }

        return $sig;
    }

    /**
     * @param  list<string>  $names
     */
    private static function firstHeader(Request $request, array $names): ?string
    {
        foreach ($names as $name) {
            $v = $request->header($name);
            if ($v !== null && $v !== '') {
                return is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
            }
        }

        return null;
    }

    private static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return trim((string) $value) === '';
    }

    // -------------------------------------------------------------------------
    // Track API
    // -------------------------------------------------------------------------

    private $siteId;

    private $apiKey;

    public function __construct()
    {
        $this->siteId = config('services.customer_io.site_id');
        $this->apiKey = config('services.customer_io.api_key');
    }

    public function createCustomer(User $user): void
    {
        if (! $this->shouldUseTrackApi()) {
            return;
        }

        $userId = $user->getKey();
        $siteId = $this->siteId;
        $apiKey = $this->apiKey;

        dispatch(function () use ($userId, $siteId, $apiKey) {
            $fresh = User::query()->with('vip')->find($userId);
            if (! $fresh || self::isBlank($fresh->uid)) {
                return;
            }

            $pathId = rawurlencode((string) $fresh->uid);
            $exp = $fresh->vip ? (float) $fresh->vip->exp : 0.0;
            $vipLevel = VipLevel::calculateLevelFromExp($exp);

            Http::withBasicAuth($siteId, $apiKey)
                ->put(self::TRACK_API_CUSTOMERS.'/'.$pathId, [
                    'email' => $fresh->email,
                    'created_at' => $fresh->created_at?->unix(),
                    'vip' => $vipLevel,
                ]);

            app(CustomerIOService::class)->sendEvent($fresh, 'sign_up', $fresh->created_at?->unix());
        });
    }

    /**
     * 登录后按当前用户表邮箱更新 Customer.io（customer id 为 uid）
     */
    public function syncEmailOnLogin(User $user): void
    {
        if (! $this->shouldUseTrackApi()) {
            return;
        }

        $userId = $user->getKey();
        $siteId = $this->siteId;
        $apiKey = $this->apiKey;

        dispatch(function () use ($userId, $siteId, $apiKey) {
            $fresh = User::query()->find($userId);
            if (! $fresh || self::isBlank($fresh->uid)) {
                return;
            }

            $email = $fresh->email;
            if ($email === null || trim((string) $email) === '') {
                return;
            }

            $pathId = rawurlencode((string) $fresh->uid);

            Http::withBasicAuth($siteId, $apiKey)
                ->put(self::TRACK_API_CUSTOMERS.'/'.$pathId, ['email' => $email]);
        });
    }

    public function update(User $user, array $data): void
    {
        if (! $this->shouldUseTrackApi()) {
            return;
        }

        $userId = $user->getKey();
        $siteId = $this->siteId;
        $apiKey = $this->apiKey;

        dispatch(function () use ($userId, $data, $siteId, $apiKey) {
            $fresh = User::query()->find($userId);
            if (! $fresh || self::isBlank($fresh->uid)) {
                return;
            }

            $pathId = rawurlencode((string) $fresh->uid);

            Http::withBasicAuth($siteId, $apiKey)
                ->put(self::TRACK_API_CUSTOMERS.'/'.$pathId, $data);
        });
    }

    public function deleteCustomer(User $user): void
    {
        if (! $this->shouldUseTrackApi()) {
            return;
        }

        try {
            Http::withBasicAuth((string) $this->siteId, (string) $this->apiKey)
                ->delete(self::TRACK_API_CUSTOMERS.'/'.$this->customerPathId($user));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>|null  $data  Customer.io 事件 data 负载（可选）
     */
    public function sendEvent(User $user, string $event, ?int $timestamp = null, ?array $data = null): void
    {
        if (! $this->shouldUseTrackApi()) {
            return;
        }

        $timestamp ??= time();

        $userId = $user->getKey();
        $siteId = $this->siteId;
        $apiKey = $this->apiKey;

        dispatch(function () use ($userId, $event, $timestamp, $siteId, $apiKey, $data) {
            $fresh = User::query()->find($userId);
            if (! $fresh || self::isBlank($fresh->uid)) {
                return;
            }

            $pathId = rawurlencode((string) $fresh->uid);

            $payload = [
                'name' => $event,
                'timestamp' => $timestamp,
            ];
            if ($data !== null && $data !== []) {
                $payload['data'] = $data;
            }

            Log::info('Customer.io send event', [
                'event' => $event,
                'uid' => $fresh->uid,
                'timestamp' => $timestamp,
                'data' => $data,
            ]);

            Http::withBasicAuth($siteId, $apiKey)
                ->post(self::TRACK_API_CUSTOMERS.'/'.$pathId.'/events', $payload);
        })->onQueue('low');
    }

    public function unsubscribe(User $user): void
    {
        $this->updateCustomer($user, ['unsubscribed' => true]);
    }

    public function updateCustomer(User $user, array $data): void
    {
        if (! $this->shouldUseTrackApi()) {
            return;
        }

        try {
            Http::withBasicAuth((string) $this->siteId, (string) $this->apiKey)
                ->put(self::TRACK_API_CUSTOMERS.'/'.$this->customerPathId($user), $data);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    private function shouldUseTrackApi(): bool
    {
        return config('services.customer_io.enabled') && $this->credentialsConfigured();
    }

    private function credentialsConfigured(): bool
    {
        return $this->siteId !== null && $this->siteId !== ''
            && $this->apiKey !== null && $this->apiKey !== '';
    }

    private function customerPathId(User $user): string
    {
        return rawurlencode((string) $user->uid);
    }
}
