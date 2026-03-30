<?php

namespace App\Services;

use App\Models\User;
use App\Models\VipLevel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CustomerIOService
{
    private $siteId;

    private $apiKey;

    public function __construct()
    {
        $this->siteId = config('services.customer_io.site_id');
        $this->apiKey = config('services.customer_io.api_key');
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

    public function createCustomer(User $user): void
    {
        if (!config('services.customer_io.enabled') || !$this->credentialsConfigured()) {
            return;
        }

        $userId = $user->getKey();
        $siteId = $this->siteId;
        $apiKey = $this->apiKey;

        dispatch(function () use ($userId, $siteId, $apiKey) {
            $fresh = User::query()->with('vip')->find($userId);
            if (!$fresh || $fresh->uid === null || $fresh->uid === '') {
                return;
            }

            $pathId = rawurlencode((string) $fresh->uid);
            $exp = $fresh->vip ? (float) $fresh->vip->exp : 0.0;
            $vipLevel = VipLevel::calculateLevelFromExp($exp);

            Http::withBasicAuth($siteId, $apiKey)
                ->put(
                    'https://track.customer.io/api/v1/customers/' . $pathId,
                    [
                        'email' => $fresh->email,
                        'created_at' => $fresh->created_at?->unix(),
                        'vip' => $vipLevel,
                        'name' => $fresh->name,
                        'receive_promotion_email' => $fresh->receive_promotion_email,
                    ]
                );

            app(CustomerIOService::class)->sendEvent($fresh, 'sign_up', $fresh->created_at?->unix());
        });
    }

    public function update(User $user, array $data): void
    {
        if (!config('services.customer_io.enabled') || !$this->credentialsConfigured()) {
            return;
        }

        $userId = $user->getKey();
        $siteId = $this->siteId;
        $apiKey = $this->apiKey;

        dispatch(function () use ($userId, $data, $siteId, $apiKey) {
            $fresh = User::query()->find($userId);
            if (!$fresh || $fresh->uid === null || $fresh->uid === '') {
                return;
            }

            $pathId = rawurlencode((string) $fresh->uid);

            Http::withBasicAuth($siteId, $apiKey)
                ->put(
                    'https://track.customer.io/api/v1/customers/' . $pathId,
                    $data
                );
        });
    }

    public function deleteCustomer(User $user): void
    {
        if (!config('services.customer_io.enabled') || !$this->credentialsConfigured()) {
            return;
        }

        try {
            $pathId = $this->customerPathId($user);
            Http::withBasicAuth($this->siteId, $this->apiKey)
                ->delete('https://track.customer.io/api/v1/customers/' . $pathId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function sendEvent(User $user, string $event, ?int $timestamp = null): void
    {
        if (!config('services.customer_io.enabled') || !$this->credentialsConfigured()) {
            return;
        }

        if ($timestamp === null) {
            $timestamp = time();
        }

        $userId = $user->getKey();
        $siteId = $this->siteId;
        $apiKey = $this->apiKey;

        dispatch(function () use ($userId, $event, $timestamp, $siteId, $apiKey) {
            $fresh = User::query()->find($userId);
            if (!$fresh || $fresh->uid === null || $fresh->uid === '') {
                return;
            }

            $pathId = rawurlencode((string) $fresh->uid);

            Http::withBasicAuth($siteId, $apiKey)
                ->post(
                    'https://track.customer.io/api/v1/customers/' . $pathId . '/events',
                    ['name' => $event, 'timestamp' => $timestamp]
                );
        })->onQueue('low');
    }

    public function unsubscribe(User $user): void
    {
        $this->updateCustomer($user, ['unsubscribed' => true]);
    }

    public function updateCustomer(User $user, array $data): void
    {
        if (!config('services.customer_io.enabled') || !$this->credentialsConfigured()) {
            return;
        }

        try {
            $pathId = $this->customerPathId($user);
            Http::withBasicAuth($this->siteId, $this->apiKey)
                ->put(
                    'https://track.customer.io/api/v1/customers/' . $pathId,
                    $data
                );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
