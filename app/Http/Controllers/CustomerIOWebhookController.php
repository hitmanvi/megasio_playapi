<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CustomerIOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Customer.io Reporting Webhook：仅处理退订（metric = unsubscribed），将 users.receive_promotion_email 置为 false。
 * 默认必须校验入站 X-Signature（与 services.customer_io.webhook 中 signing_secret 配置）；
 * 未设置环境变量时 verify_signature 为 true，仅联调可显式关闭。
 *
 * @see https://customer.io/docs/journeys/webhooks/
 */
class CustomerIOWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse|Response
    {
        Log::info('Customer.io webhook request', ['request' => $request->all()]);
        if (! config('services.customer_io.webhook.enabled')) {
            abort(404);
        }

        $raw = $request->getContent();

        if (config('services.customer_io.webhook.verify_signature')) {
            $secret = (string) (config('services.customer_io.webhook.signing_secret') ?? '');
            if ($secret === '') {
                Log::error('Customer.io webhook verify_signature enabled but signing secret empty');

                return response('Webhook signing not configured', 503);
            }
            $signature = $request->header('X-Signature');
            if (! CustomerIOService::verifyWebhookSignature($raw, $signature, $secret)) {
                return response('Invalid signature', 401);
            }
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON body'], 422);
        }

        $metric = strtolower((string) ($payload['metric'] ?? ''));
        if ($metric !== 'unsubscribed') {
            return response()->json(['ok' => true]);
        }

        return $this->handleUnsubscribed($payload);
    }

    private function handleUnsubscribed(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            Log::warning('Customer.io unsubscribed webhook: missing data');

            return response()->json(['ok' => true]);
        }

        $uid = $this->stringOrNull($data['customer_id'] ?? null)
            ?? $this->stringOrNull(is_array($data['identifiers'] ?? null) ? ($data['identifiers']['id'] ?? null) : null);

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

    private function stringOrNull(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
