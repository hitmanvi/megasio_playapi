<?php

namespace App\Listeners;

use App\Events\DepositCreated;
use App\Services\OpenSearchService;
use Illuminate\Support\Facades\Log;

class IndexDepositCreatedToOpenSearch
{
    public function handle(DepositCreated $event): void
    {
        $service = new OpenSearchService();
        if (!$service->isEnabled()) {
            return;
        }

        $deposit = $event->deposit;
        $deposit->loadMissing('user.agentLink');
        $user = $deposit->user;
        $agentId = $user?->agentLink?->agent_id ?? null;

        $result = $service->indexEvent('deposit_created', [
            'user_id' => $deposit->user_id,
            'uid' => $user?->uid,
            'order_no' => $deposit->order_no,
            'amount' => (float) $deposit->amount,
            'currency' => $deposit->currency,
            'agent_id' => $agentId,
            'agent_link_id' => $user?->agent_link_id,
            'status' => $deposit->status,
            'source' => 'event',
        ], 'deposit_' . $deposit->id);

        if (!$result['success']) {
            Log::warning('OpenSearch index deposit_created failed', [
                'deposit_id' => $deposit->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }
    }
}
