<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kochava Post-Install Event Service
 *
 * @see https://support.kochava.com/articles/server-to-server-integration/185-post-install-event-setup
 */
class KochavaService
{
    protected string $endpoint = 'https://control.kochava.com/track/json';

    protected ?string $appId = null;

    protected bool $enabled = false;

    public function __construct()
    {
        $this->appId = config('services.kochava.app_id');
        $this->enabled = config('services.kochava.enabled', false) && !empty($this->appId);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send a post-install event to Kochava.
     *
     * @param string $eventName Event name (e.g. register, begin_checkout, purchase)
     * @param array $eventData Additional event data (e.g. currency, amount, user_id)
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

        $data = [
            'usertime' => $deviceInfo['usertime'] ?? time(),
            'app_version' => $deviceInfo['app_version'] ?? '',
            'device_ver' => $deviceInfo['device_ver'] ?? '',
            'device_ids' => $deviceIds,
            'device_ua' => $deviceUa,
            'event_name' => $eventName,
            'origination_ip' => $originationIp,
            'currency' => $eventData['currency'] ?? config('app.currency', 'USD'),
            'event_data' => $eventData,
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
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint, $payload);

            if (!$response->successful()) {
                Log::warning('Kochava: event failed', [
                    'event' => $eventName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            Log::debug('Kochava: event sent', ['event' => $eventName]);
            return true;
        } catch (Throwable $e) {
            Log::error('Kochava: exception', [
                'event' => $eventName,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Extract device info from deposit.device_info or extra_info (fallback).
     */
    public static function deviceInfoFromDeposit($deposit): array
    {
        $extra = $deposit->device_info ?? $deposit->extra_info ?? [];
        $deviceIds = $extra['device_ids'] ?? $extra['kochava_device_ids'] ?? [];
        if (is_string($deviceIds)) {
            $deviceIds = json_decode($deviceIds, true) ?: [];
        }
        if (empty($deviceIds) && !empty($extra['idfa'])) {
            $deviceIds = ['idfa' => $extra['idfa']];
        }
        if (empty($deviceIds) && !empty($extra['android_id'])) {
            $deviceIds = ['android_id' => $extra['android_id']];
        }

        return [
            'kochava_device_id' => $extra['kochava_device_id'] ?? '',
            'device_ids' => $deviceIds,
            'device_ua' => $extra['device_ua'] ?? '',
            'origination_ip' => $deposit->user_ip ?? $extra['origination_ip'] ?? '0.0.0.0',
            'app_version' => $extra['app_version'] ?? '',
            'device_ver' => $extra['device_ver'] ?? '',
            'usertime' => $extra['usertime'] ?? time(),
            'fbc' => $extra['fbc'] ?? '',
            'fbp' => $extra['fbp'] ?? '',
        ];
    }
}
