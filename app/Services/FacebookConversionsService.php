<?php

namespace App\Services;

use App\Models\AgentLink;
use App\Models\Deposit;
use App\Models\User;
use FacebookAds\Api;
use FacebookAds\Object\ServerSide\ActionSource;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Object\ServerSide\UserData;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Facebook Conversions API Service (using facebook-php-business-sdk)
 *
 * @see https://developers.facebook.com/docs/marketing-api/conversions-api/using-the-api
 * @see https://github.com/facebook/facebook-php-business-sdk
 */
class FacebookConversionsService
{
    protected ?string $pixelId = null;

    protected ?string $accessToken = null;

    protected bool $enabled = false;

    public function __construct(?AgentLink $link = null)
    {
        if ($link && $link->hasFacebookConversions()) {
            $cfg = $link->getFacebookConfig();
            $this->pixelId = $cfg['pixel_id'] ?? null;
            $this->accessToken = $cfg['access_token'] ?? null;
        } else {
            $this->pixelId = config('services.facebook_conversions.pixel_id');
            $this->accessToken = config('services.facebook_conversions.access_token');
        }
        $this->enabled = config('services.facebook_conversions.enabled', false)
            && !empty($this->pixelId)
            && !empty($this->accessToken);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send event to Facebook Conversions API.
     *
     * @param string $eventName Meta Pixel 标准事件：CompleteRegistration, InitiateCheckout, Purchase, FirstDeposit
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

        try {
            Api::init(null, null, $this->accessToken);

            $serverUserData = $this->buildUserData($userData);
            $eventSourceUrl = $userData['event_source_url'] ?? config('app.web_url', 'https://example.com');

            $serverEvent = (new Event())
                ->setEventName($eventName)
                ->setEventTime($userData['event_time'] ?? time())
                ->setActionSource(ActionSource::WEBSITE)
                ->setEventSourceUrl($eventSourceUrl)
                ->setUserData($serverUserData);

            if (!empty($customData)) {
                $serverCustomData = (new CustomData());
                if (isset($customData['currency'])) {
                    $serverCustomData->setCurrency(strtolower($customData['currency']));
                }
                if (isset($customData['value'])) {
                    $serverCustomData->setValue((float) $customData['value']);
                }
                $serverEvent->setCustomData($serverCustomData);
            }

            if ($eventId) {
                $serverEvent->setEventId($eventId);
            }

            $request = (new EventRequest($this->pixelId))
                ->setEvents([$serverEvent]);

            $response = $request->execute();

            Log::debug('Facebook Conversions: event sent', [
                'event' => $eventName,
                'events_received' => $response->getEventsReceived(),
            ]);
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
     * Build UserData object. SDK handles hashing automatically.
     */
    protected function buildUserData(array $data): UserData
    {
        $userData = (new UserData())
            ->setClientIpAddress($data['client_ip_address'] ?? $data['current_ip'] ?? $data['origination_ip'] ?? '')
            ->setClientUserAgent($data['client_user_agent'] ?? $data['device_ua'] ?? '');

        if (!empty($data['em'])) {
            $userData->setEmails([$data['em']]);
        }
        if (!empty($data['ph'])) {
            $userData->setPhones([$this->normalizePhone($data['ph'])]);
        }
        if (!empty($data['fbc'])) {
            $userData->setFbc($data['fbc']);
        }
        if (!empty($data['fbp'])) {
            $userData->setFbp($data['fbp']);
        }

        return $userData;
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
            'client_ip_address' => $deviceInfo['current_ip'] ?? $deviceInfo['origination_ip'] ?? '',
            'client_user_agent' => $deviceInfo['device_ua'] ?? '',
            'event_source_url' => $deviceInfo['event_source_url'] ?? null,
            'fbc' => $deviceInfo['fbc'] ?? '',
            'fbp' => $deviceInfo['fbp'] ?? '',
        ];
        if ($user->email) {
            $data['em'] = $user->email;
        }
        if ($user->phone) {
            $data['ph'] = ($user->area_code ?: '') . $user->phone;
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
            'client_ip_address' => $deviceInfo['current_ip'] ?? $deposit->user_ip ?? $deviceInfo['origination_ip'] ?? '',
            'client_user_agent' => $deviceInfo['device_ua'] ?? '',
            'event_source_url' => $deviceInfo['event_source_url'] ?? null,
            'fbc' => $deviceInfo['fbc'] ?? '',
            'fbp' => $deviceInfo['fbp'] ?? '',
        ];
        if ($user && $user->email) {
            $data['em'] = $user->email;
        }
        if ($user && $user->phone) {
            $data['ph'] = ($user->area_code ?: '') . $user->phone;
        }
        return $data;
    }
}
