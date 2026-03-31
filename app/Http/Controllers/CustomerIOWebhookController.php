<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CustomerIOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CustomerIOWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse|Response
    {
        if (!config('services.customer_io.webhook.enabled')) {
            abort(404);
        }

        $secret = (string) (config('services.customer_io.webhook.signing_secret') ?? '');
        if ($secret === '') {
            Log::error('Customer.io webhook is enabled but CUSTOMER_IO_WEBHOOK_SIGNING_SECRET is empty');

            return response('Webhook signing not configured', 503);
        }

        $raw = $request->getContent();
        $signature = $request->header('X-Signature');
        if (!CustomerIOService::verifyWebhookSignature($raw, $signature, $secret)) {
            return response('Invalid signature', 401);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return response()->json(['message' => 'Invalid JSON body'], 422);
        }

        $items = array_is_list($decoded) ? $decoded : [$decoded];
        foreach ($items as $item) {
            if (is_array($item)) {
                $this->processWebhookItem($item);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function processWebhookItem(array $item): void
    {
        if (($item['action'] ?? null) === 'set_receive_promotion_email') {
            $uid = $this->stringOrNull($item['user_id'] ?? $item['userId'] ?? null);
            if ($uid !== null && array_key_exists('value', $item)) {
                $this->setReceivePromotionEmailByUid($uid, filter_var($item['value'], FILTER_VALIDATE_BOOLEAN));

                return;
            }
        }

        $uid = $this->resolveCustomerUid($item);
        if ($uid === null) {
            return;
        }

        $traits = $item['traits'] ?? $item['properties'] ?? [];
        if (is_array($traits)) {
            if (array_key_exists('receive_promotion_email', $traits)) {
                $this->setReceivePromotionEmailByUid($uid, filter_var($traits['receive_promotion_email'], FILTER_VALIDATE_BOOLEAN));

                return;
            }
            if (array_key_exists('unsubscribed', $traits)) {
                $this->setReceivePromotionEmailByUid($uid, !filter_var($traits['unsubscribed'], FILTER_VALIDATE_BOOLEAN));

                return;
            }
        }

        $event = $item['event'] ?? $item['metric'] ?? $item['name'] ?? null;
        if (!is_string($event) || $event === '') {
            return;
        }

        $el = strtolower($event);
        if (str_contains($el, 'unsubscrib') || str_contains($el, 'spam') || str_contains($el, 'suppress')) {
            $this->setReceivePromotionEmailByUid($uid, false);

            return;
        }
        if (str_contains($el, 'resubscrib') || str_contains($el, 'subscription_restored')) {
            $this->setReceivePromotionEmailByUid($uid, true);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveCustomerUid(array $item): ?string
    {
        foreach (['userId', 'user_id', 'customer_id'] as $key) {
            if (isset($item[$key])) {
                $v = $this->stringOrNull($item[$key]);
                if ($v !== null) {
                    return $v;
                }
            }
        }

        if (isset($item['customer']) && is_array($item['customer'])) {
            $c = $item['customer'];
            foreach (['id', 'userId', 'user_id'] as $key) {
                $v = $this->stringOrNull($c[$key] ?? null);
                if ($v !== null) {
                    return $v;
                }
            }
        }

        if (isset($item['data']) && is_array($item['data'])) {
            return $this->resolveCustomerUid($item['data']);
        }

        return null;
    }

    private function stringOrNull(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function setReceivePromotionEmailByUid(string $uid, bool $value): void
    {
        $updated = User::query()->where('uid', $uid)->update(['receive_promotion_email' => $value]);
        if ($updated === 0) {
            Log::warning('Customer.io webhook: no user found for uid', ['uid' => $uid]);
        }
    }
}
