<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Facebook Conversions API Service
 *
 * @see https://developers.facebook.com/docs/marketing-api/conversions-api/using-the-api
 */
class FacebookConversionsService
{
    protected string $baseUrl = 'https://graph.facebook.com';

    protected ?string $pixelId = null;

    protected ?string $accessToken = null;

    protected string $apiVersion = 'v21.0';

    protected bool $enabled = false;

    public function __construct()
    {
        $this->pixelId = config('services.facebook_conversions.pixel_id');
        $this->accessToken = config('services.facebook_conversions.access_token');
        $this->enabled = !empty($this->pixelId) && !empty($this->accessToken);
    }

    /**
     * Send event to Facebook Conversions API.
     *
     * @param string $eventName CompleteRegistration, InitiateCheckout, Purchase
     * @param array $userData client_ip_address, client_user_agent, em, ph, fbc, fbp
     * @param array $customData currency, value, etc.
     * @param string|null $eventId Optional event ID for deduplication
     * @return bool Success
     */
    public function sendEvent(
        string $eventName,
        array $userData = [],
        array $customData = [],
        ?string $eventId = null
    ): bool {
        if (!$this->enabled) {
            Log::debug('Facebook Conversions: disabled, skipping event', ['event' => $eventName]);
            return false;
        }

        $event = [
            'event_name' => $eventName,
            'event_time' => $userData['event_time'] ?? time(),
            'action_source' => $userData['action_source'] ?? 'app',
            'user_data' => $this->buildUserData($userData),
        ];

        if (!empty($customData)) {
            $event['custom_data'] = $customData;
        }

        if ($eventId) {
            $event['event_id'] = $eventId;
        }

        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->pixelId}/events?access_token=" . urlencode($this->accessToken);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, ['data' => [$event]]);

            if (!$response->successful()) {
                Log::warning('Facebook Conversions: event failed', [
                    'event' => $eventName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $body = $response->json();
            if (!empty($body['error'])) {
                Log::warning('Facebook Conversions: API error', [
                    'event' => $eventName,
                    'error' => $body['error'],
                ]);
                return false;
            }

            Log::debug('Facebook Conversions: event sent', ['event' => $eventName]);
            return true;
        } catch (Throwable $e) {
            Log::error('Facebook Conversions: exception', [
                'event' => $eventName,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build user_data object. Hashes em, ph with SHA256 per Facebook requirements.
     */
    protected function buildUserData(array $data): array
    {
        $userData = [
            'client_ip_address' => $data['client_ip_address'] ?? $data['origination_ip'] ?? '',
            'client_user_agent' => $data['client_user_agent'] ?? $data['device_ua'] ?? '',
        ];

        if (!empty($data['em'])) {
            $userData['em'] = [hash('sha256', $this->normalizeEmail($data['em']))];
        }
        if (!empty($data['ph'])) {
            $userData['ph'] = [hash('sha256', $this->normalizePhone($data['ph']))];
        }
        if (!empty($data['fbc'])) {
            $userData['fbc'] = $data['fbc'];
        }
        if (!empty($data['fbp'])) {
            $userData['fbp'] = $data['fbp'];
        }

        return array_filter($userData);
    }

    protected function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Build user_data from User model and deviceInfo.
     */
    public static function userDataFromUser(User $user, array $deviceInfo = []): array
    {
        $data = [
            'client_ip_address' => $deviceInfo['origination_ip'] ?? '',
            'client_user_agent' => $deviceInfo['device_ua'] ?? '',
            'fbc' => $deviceInfo['fbc'] ?? '',
            'fbp' => $deviceInfo['fbp'] ?? '',
        ];
        if ($user->email) {
            $data['em'] = $user->email;
        }
        if ($user->phone) {
            $data['ph'] = ($user->area_code ? $user->area_code : '') . $user->phone;
        }
        return $data;
    }

    /**
     * Build user_data from Deposit and deviceInfo.
     */
    public static function userDataFromDeposit(Deposit $deposit, array $deviceInfo = []): array
    {
        $user = $deposit->user;
        $data = [
            'client_ip_address' => $deposit->user_ip ?? $deviceInfo['origination_ip'] ?? '',
            'client_user_agent' => $deviceInfo['device_ua'] ?? '',
            'fbc' => $deviceInfo['fbc'] ?? '',
            'fbp' => $deviceInfo['fbp'] ?? '',
        ];
        if ($user && $user->email) {
            $data['em'] = $user->email;
        }
        if ($user && $user->phone) {
            $data['ph'] = ($user->area_code ? $user->area_code : '') . $user->phone;
        }
        return $data;
    }
}
