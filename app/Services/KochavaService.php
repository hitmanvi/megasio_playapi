<?php

namespace App\Services;

use App\Models\AgentLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kochava Post-Install Event Service
 *
 * Event mapping (per https://support.kochava.com/articles/reference-information/2213-post-install-event-examples):
 * - register → Registration Complete (standard)
 * - begin_checkout → Checkout Start (standard)
 * - purchase → Purchase (standard)
 * - first_purchase → First Deposit (custom)
 *
 * @see https://support.kochava.com/articles/server-to-server-integration/185-post-install-event-setup
 */
class KochavaService
{
    protected const EVENT_NAME_MAP = [
        'register' => 'Registration Complete',
        'begin_checkout' => 'Checkout Start',
        'purchase' => 'Purchase',
        'first_purchase' => 'First Deposit',
    ];

    protected string $endpoint = 'https://control.kochava.com/track/json';

    protected ?string $appId = null;

    protected bool $enabled = false;

    public function __construct(?AgentLink $link = null)
    {
        if ($link && $link->hasKochava()) {
            $cfg = $link->getKochavaConfig();
            $this->appId = $cfg['app_id'] ?? null;
        } else {
            $this->appId = config('services.kochava.app_id');
        }
        $this->enabled = config('services.kochava.enabled', false) && !empty($this->appId);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send a post-install event to Kochava.
     *
     * @param string $eventName Internal event name (register, begin_checkout, purchase, first_purchase) or custom
     * @param array $eventData Event data (uid, order_no, amount, currency, etc.) - will be mapped to Kochava Raw Keys
     * @param array $deviceInfo Required: kochava_device_id, device_ids, device_ua, origination_ip
     *                           Optional: app_version, device_ver, usertime, gdpr_privacy_consent, app_tracking_transparency
     * @return bool Success
     */
    public function sendEvent(string $eventName, array $eventData = [], array $deviceInfo = []): bool
    {
        if (!$this->enabled) {
            Log::debug('Kochava: disabled, skipping event', ['event' => $eventName]);
            return false;
        }

        $kochavaDeviceId = $deviceInfo['kochava_device_id'] ?? '';
        $deviceIds = $deviceInfo['device_ids'] ?? [];
        $deviceUa = $deviceInfo['device_ua'] ?? '';
        $originationIp = $deviceInfo['origination_ip'] ?? '0.0.0.0';

        // Kochava requires at least one of kochava_device_id or device_ids (with at least one id)
        $hasDeviceId = !empty($kochavaDeviceId) || !empty($deviceIds);
        if (!$hasDeviceId) {
            Log::debug('Kochava: missing device identifiers, skipping event', ['event' => $eventName]);
            return false;
        }

        $kochavaEventName = self::EVENT_NAME_MAP[$eventName] ?? $eventName;
        $kochavaEventData = $this->mapEventDataToKochavaRawKeys($eventName, $eventData);

        $data = [
            'usertime' => $deviceInfo['usertime'] ?? time(),
            'app_version' => $deviceInfo['app_version'] ?? '',
            'device_ver' => $deviceInfo['device_ver'] ?? '',
            'device_ids' => $deviceIds,
            'device_ua' => $deviceUa,
            'event_name' => $kochavaEventName,
            'origination_ip' => $originationIp,
            'currency' => $eventData['currency'] ?? config('app.currency', 'USD'),
            'event_data' => $kochavaEventData,
        ];

        if (!empty($deviceInfo['device_limit_tracking'])) {
            $data['device_limit_tracking'] = $deviceInfo['device_limit_tracking'];
        }

        if (!empty($deviceInfo['gdpr_privacy_consent'])) {
            $data['gdpr_privacy_consent'] = $deviceInfo['gdpr_privacy_consent'];
        }

        if (!empty($deviceInfo['app_tracking_transparency'])) {
            $data['app_tracking_transparency'] = $deviceInfo['app_tracking_transparency'];
        }

        $payload = [
            'action' => 'event',
            'kochava_app_id' => $this->appId,
            'kochava_device_id' => $kochavaDeviceId,
            'data' => $data,
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint, $payload);

            if (!$response->successful()) {
                Log::warning('Kochava: event failed', [
                    'event' => $kochavaEventName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            Log::debug('Kochava: event sent', ['event' => $kochavaEventName]);
            return true;
        } catch (Throwable $e) {
            Log::error('Kochava: exception', [
                'event' => $kochavaEventName,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Map internal event data to Kochava Raw Keys per Post-Install Event Examples.
     *
     * @see https://support.kochava.com/articles/reference-information/2213-post-install-event-examples
     */
    protected function mapEventDataToKochavaRawKeys(string $eventName, array $data): array
    {
        $mapped = [];
        $userId = $data['user_id'] ?? $data['uid'] ?? null;
        if ($userId) {
            $mapped['user_id'] = $userId;
        }

        foreach (['currency', 'user_name', 'referral_from', 'content_id', 'order_id', 'name', 'price'] as $key) {
            if (array_key_exists($key, $data)) {
                $mapped[$key] = $data[$key];
            }
        }

        // Legacy: amount -> price, order_no -> content_id/order_id
        if (!isset($mapped['price']) && array_key_exists('amount', $data)) {
            $mapped['price'] = (float) $data['amount'];
        }
        if (!isset($mapped['content_id']) && !empty($data['order_no'])) {
            $mapped['content_id'] = $data['order_no'];
        }
        if (!isset($mapped['order_id']) && !empty($data['order_no'])) {
            $mapped['order_id'] = $data['order_no'];
        }

        return $mapped;
    }
}
