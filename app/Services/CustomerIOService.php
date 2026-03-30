<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\UserVipService;
class CustomerIOService
{
    private $siteId;
    private $apiKey;

    public function __construct()
    {
        $this->siteId = config('services.customer_io.site_id');
        $this->apiKey = config('services.customer_io.api_key');
    }

    public function createCustomer($user)
    {
        if(!config('services.customer_io.enabled')) {
            return;
        }
        dispatch(function() use ($user){
            Http::withBasicAuth($this->siteId, $this->apiKey)
                ->put("https://track.customer.io/api/v1/customers/{$user->uid}",
                    ['email' => $user->email, 'created_at' => $user->created_at->unix(), 'vip' => (new UserVipService())->getLevel($user->user_id) ?? 1],
                );
            $this->sendEvent($user, 'sign_up', $user->created_at->unix());
        });
    }

    public function update($user, $data)
    {
        if(!config('services.customer_io.enabled')) {
            return;
        }
        dispatch(function() use ($user, $data){
            Http::withBasicAuth($this->siteId, $this->apiKey)
                ->put("https://track.customer.io/api/v1/customers/{$user->uid}",
                    $data,
                );
        });
    }

    public function deleteCustomer($user)
    {
        if(!config('services.customer_io.enabled')) {
            return;
        }
        try{
            Http::withBasicAuth($this->siteId, $this->apiKey)
                ->delete("https://track.customer.io/api/v1/customers/{$user->uid}");
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

    }

    public function sendEvent($user, $event, $timestamp=null)
    {
        if(!config('services.customer_io.enabled')) {
            return;
        }

        if(!$timestamp) {
            $timestamp = time();
        }
        dispatch(function () use ($user, $event, $timestamp) {
            Http::withBasicAuth($this->siteId, $this->apiKey)
                ->post("https://track.customer.io/api/v1/customers/{$user->uid}/events",
                    ['name' => $event, 'timestamp' => $timestamp],
                );
        })->onQueue('low');
    }

    public function unsubscribe($user)
    {
        $data = ['unsubscribed' => true];
        $this->updateCustomer($user, $data);
    }

    public function updateCustomer($user, $data)
    {
        if(!config('services.customer_io.enabled')) {
            return;
        }
        try{
            Http::withBasicAuth($this->siteId, $this->apiKey)
                ->put("https://track.customer.io/api/v1/customers/{$user->uid}", $data);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}