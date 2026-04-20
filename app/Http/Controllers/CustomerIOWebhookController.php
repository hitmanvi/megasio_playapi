<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CustomerIOService;
use App\Services\PromotionCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Customer.io Reporting Webhook：支持 metric = unsubscribed（更新用户退订）、metric = sent（邮件发送等，可含 request 包裹层）。
 * 默认必须校验入站 X-Signature（与 services.customer_io.webhook 中 signing_secret 配置）；
 * 未设置环境变量时 verify_signature 为 true，仅联调可显式关闭。
 *
 * @see https://customer.io/docs/journeys/webhooks/
 */
class CustomerIOWebhookController extends Controller
{
    protected PromotionCodeService $promotionCodeService;

    public function __construct()
    {
        $this->promotionCodeService = new PromotionCodeService;
    }

    public function handle(Request $request): JsonResponse|Response
    {
        Log::info('Customer.io webhook request', ['request' => $request->all(), 'headers' => $request->headers->all()]);
        if (! config('services.customer_io.webhook.enabled')) {
            abort(404);
        }

        $signatureResponse = CustomerIOService::ensureInboundWebhookSignature($request);
        if ($signatureResponse !== null) {
            return $signatureResponse;
        }

        $raw = $request->getContent();
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON body'], 422);
        }

        $event = $this->normalizeReportingEvent($payload);
        $metric = strtolower((string) ($event['metric'] ?? ''));

        return match ($metric) {
            'unsubscribed' => $this->handleUnsubscribed($event),
            'sent' => $this->handleSent($event),
            default => response()->json(['ok' => true]),
        };
    }

    /**
     * Customer.io 部分上报体为 { "request": { "metric", "data", "event_id", ... } }，与顶层字段形式统一。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeReportingEvent(array $payload): array
    {
        if (isset($payload['request']) && is_array($payload['request'])) {
            return $payload['request'];
        }

        return $payload;
    }

    /**
     * 邮件/消息发送（metric = sent）：若 campaign 绑定兑换码则为用户写入 pending 领取记录。
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSent(array $event): JsonResponse
    {
        $data = $event['data'] ?? null;
        if (! is_array($data)) {
            return response()->json(['ok' => true]);
        }

        $campaignId = $data['campaign_id'] ?? null;
        $uid = $this->resolveCustomerIdentifierFromData($data);

        if ($uid === null) {
            Log::warning('Customer.io sent webhook: no uid in data.customer_id / data.identifiers.id');

            return response()->json(['ok' => true]);
        }

        $user = User::query()->where('uid', $uid)->first();
        if (! $user) {
            Log::warning('Customer.io sent webhook: no user for uid', ['uid' => $uid]);

            return response()->json(['ok' => true]);
        }

        $this->promotionCodeService->ensurePendingClaimForCustomerIoCampaign((int) $user->id, $campaignId);

        return response()->json(['ok' => true]);
    }

    private function handleUnsubscribed(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            Log::warning('Customer.io unsubscribed webhook: missing data');

            return response()->json(['ok' => true]);
        }

        $uid = $this->resolveCustomerIdentifierFromData($data);

        if ($uid === null) {
            Log::warning('Customer.io unsubscribed webhook: no uid in data.customer_id / data.identifiers.id');

            return response()->json(['ok' => true]);
        }

        $updated = User::query()->where('uid', $uid)->update(['receive_promotion_email' => false]);
        if ($updated === 0) {
            Log::warning('Customer.io unsubscribed: no user for uid', ['uid' => $uid]);
        } else {
            Log::info('Customer.io unsubscribed: receive_promotion_email cleared', ['uid' => $uid]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomerIdentifierFromData(array $data): ?string
    {
        return $this->stringOrNull($data['customer_id'] ?? null)
            ?? $this->stringOrNull(is_array($data['identifiers'] ?? null) ? ($data['identifiers']['id'] ?? null) : null);
    }

    private function stringOrNull(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
