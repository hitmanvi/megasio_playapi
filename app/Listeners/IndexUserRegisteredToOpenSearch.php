<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\OpenSearchService;
use Illuminate\Support\Facades\Log;

class IndexUserRegisteredToOpenSearch
{
    public function handle(UserRegistered $event): void
    {
        $service = new OpenSearchService();
        if (!$service->isEnabled()) {
            return;
        }

        $user = $event->user;
        $user->loadMissing('agentLink');
        $agentId = $user->agentLink?->agent_id ?? null;

        $result = $service->indexEvent('user_registered', [
            'user_id' => $user->id,
            'uid' => $user->uid,
            'email' => $user->email,
            'agent_id' => $agentId,
            'agent_link_id' => $user->agent_link_id,
            'source' => 'event',
        ], 'user_' . $user->id);

        if (!$result['success']) {
            Log::warning('OpenSearch index user_registered failed', [
                'user_id' => $user->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }
    }
}
