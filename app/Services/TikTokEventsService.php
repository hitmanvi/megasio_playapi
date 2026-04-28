<?php

namespace App\Services;

use App\Models\AgentLink;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TikTok Events API Service.
 *
 * @see https://business-api.tiktok.com/portal/docs/report-app-web-offline-or-crm-events/v1.3
 */
class TikTokEventsService
{
    protected string $endpoint;

    protected ?string $pixelCode = null;

    protected ?string $accessToken = null;

    protected ?string $testEventCode = null;

    protected string $eventSource = 'web';

    protected bool $enabled = false;

    public function __construct(?AgentLink $link = null)
    {
        if ($link && $link->hasTikTokEvents()) {
            $cfg = $link->getTikTokConfig();
            $this->pixelCode = $cfg['pixel_code'] ?? null;
            $this->accessToken = $cfg['access_token'] ?? null;
            $this->testEventCode = $cfg['test_event_code'] ?? null;
            $this->eventSource = $cfg['event_source'] ?? config('services.tiktok_events.event_source', 'web');
        } else {
            $this->pixelCode = config('services.tiktok_events.pixel_code');
            $this->accessToken = config('services.tiktok_events.access_token');
            $this->testEventCode = config('services.tiktok_events.test_event_code');
            $this->eventSource = config('services.tiktok_events.event_source', 'web');
        }

        $this->endpoint = rtrim(
            (string) config('services.tiktok_events.endpoint', 'https://business-api.tiktok.com/open_api/v1.3/event/track/'),
            '/'
        ).'/';

        $this->enabled = (bool) config('services.tiktok_events.enabled', false)
            && ! empty($this->pixelCode)
            && ! empty($this->accessToken);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send one event to TikTok Events API.
     *
     * @param string $event TikTok event name (e.g. CompletePayment, CompleteRegistration)
     * @param array $userData Supported keys: email/em, phone/ph, external_id/user_id/uid, ttclid, ttp, ip/client_ip_address, user_agent/client_user_agent
     * @param array $properties Event properties, usually includes value/currency/content data
     * @param string|null $eventId Optional event_id for deduplication
     * @param int|null $eventTime Unix timestamp, defaults to now
     * @param array $context Optional keys: event_source, event_source_url, page
     */
    public function sendEvent(
        string $event,
        array $userData = [],
        array $properties = [],
        ?string $eventId = null,
        ?int $eventTime = null,
        array $context = []
    ): bool {
        if (! $this->enabled) {
            Log::debug('TikTok Events: disabled, skipping event', [
                'event' => $event,
            ]);
            return false;
        }

        $user = $this->buildUserPayload($userData);
        $eventPayload = [
            'event' => $event,
            'event_time' => $eventTime ?? time(),
            'user' => $user,
        ];

        if ($eventId) {
            $eventPayload['event_id'] = $eventId;
        }

        if (! empty($properties)) {
            $eventPayload['properties'] = $properties;
        }

        if (! empty($context['page']) && is_array($context['page'])) {
            $eventPayload['page'] = $context['page'];
        } elseif (! empty($context['event_source_url'])) {
            $eventPayload['page'] = ['url' => (string) $context['event_source_url']];
        }

        $payload = [
            'event_source' => (string) ($context['event_source'] ?? $this->eventSource),
            'event_source_id' => $this->pixelCode,
            'data' => [$eventPayload],
        ];

        if (! empty($this->testEventCode)) {
            $payload['test_event_code'] = $this->testEventCode;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Access-Token' => (string) $this->accessToken,
                ])
                ->post($this->endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('TikTok Events: HTTP request failed', [
                    'event' => $event,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $json = $response->json();
            $code = (int) ($json['code'] ?? -1);
            if ($code !== 0) {
                Log::warning('TikTok Events: API responded with failure', [
                    'event' => $event,
                    'code' => $json['code'] ?? null,
                    'message' => $json['message'] ?? null,
                    'request_id' => $json['request_id'] ?? null,
                    'body' => $json,
                ]);
                return false;
            }

            Log::debug('TikTok Events: event sent', [
                'event' => $event,
                'request_id' => $json['request_id'] ?? null,
            ]);
            return true;
        } catch (Throwable $e) {
            Log::error('TikTok Events: exception', [
                'event' => $event,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function buildUserPayload(array $userData): array
    {
        $payload = [];

        $email = $userData['email'] ?? $userData['em'] ?? null;
        if (! empty($email)) {
            $payload['email'] = $this->sha256IfNeeded(mb_strtolower(trim((string) $email)));
        }

        $phone = $userData['phone_number'] ?? $userData['phone'] ?? $userData['ph'] ?? null;
        if (! empty($phone)) {
            $payload['phone_number'] = $this->sha256IfNeeded($this->normalizePhone((string) $phone));
        }

        $externalId = $userData['external_id'] ?? $userData['uid'] ?? $userData['user_id'] ?? null;
        if (! empty($externalId)) {
            $payload['external_id'] = $this->sha256IfNeeded((string) $externalId);
        }

        if (! empty($userData['ttclid'])) {
            $payload['ttclid'] = (string) $userData['ttclid'];
        }
        if (! empty($userData['ttp'])) {
            $payload['ttp'] = (string) $userData['ttp'];
        }

        $ip = $userData['ip'] ?? $userData['client_ip_address'] ?? $userData['current_ip'] ?? $userData['origination_ip'] ?? null;
        if (! empty($ip)) {
            $payload['ip'] = (string) $ip;
        }

        $userAgent = $userData['user_agent'] ?? $userData['client_user_agent'] ?? $userData['device_ua'] ?? null;
        if (! empty($userAgent)) {
            $payload['user_agent'] = (string) $userAgent;
        }

        return $payload;
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone) ?: '';
    }

    protected function sha256IfNeeded(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return $v;
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $v) === 1) {
            return strtolower($v);
        }

        return hash('sha256', $v);
    }

    /**
     * Build common user_data from User model and device info.
     */
    public static function userDataFromUser(User $user, array $deviceInfo = []): array
    {
        $data = [
            'client_ip_address' => $deviceInfo['current_ip'] ?? $deviceInfo['origination_ip'] ?? '',
            'client_user_agent' => $deviceInfo['device_ua'] ?? '',
            'ttclid' => $deviceInfo['ttclid'] ?? '',
            'ttp' => $deviceInfo['ttp'] ?? '',
            'event_source_url' => $deviceInfo['event_source_url'] ?? null,
        ];

        if ($user->email) {
            $data['email'] = $user->email;
        }
        if ($user->phone) {
            $data['phone_number'] = ($user->area_code ?: '').$user->phone;
        }

        $data['external_id'] = (string) ($user->uid ?: $user->id);

        return $data;
    }

    /**
     * Build common user_data from Deposit and device info.
     */
    public static function userDataFromDeposit(Deposit $deposit, array $deviceInfo = []): array
    {
        $user = $deposit->user;
        $data = [
            'client_ip_address' => $deviceInfo['current_ip'] ?? $deposit->user_ip ?? $deviceInfo['origination_ip'] ?? '',
            'client_user_agent' => $deviceInfo['device_ua'] ?? '',
            'ttclid' => $deviceInfo['ttclid'] ?? '',
            'ttp' => $deviceInfo['ttp'] ?? '',
            'event_source_url' => $deviceInfo['event_source_url'] ?? null,
        ];

        if ($user && $user->email) {
            $data['email'] = $user->email;
        }
        if ($user && $user->phone) {
            $data['phone_number'] = ($user->area_code ?: '').$user->phone;
        }
        if ($user) {
            $data['external_id'] = (string) ($user->uid ?: $user->id);
        }

        return $data;
    }
}
