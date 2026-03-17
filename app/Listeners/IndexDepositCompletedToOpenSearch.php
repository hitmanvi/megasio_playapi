<?php

namespace App\Listeners;

use App\Events\DepositCompleted;
use App\Services\OpenSearchService;
use Illuminate\Support\Facades\Log;

class IndexDepositCompletedToOpenSearch
{
    public function handle(DepositCompleted $event): void
    {
        $service = new OpenSearchService();
        if (!$service->isEnabled()) {
            return;
        }

        $deposit = $event->deposit;
        $deposit->loadMissing('user.agentLink');
        $user = $deposit->user;
        $agentId = $user?->agentLink?->agent_id ?? null;

        $timestamp = ($deposit->completed_at ?? $deposit->created_at)?->toIso8601String() ?? now()->toIso8601String();

        $result = $service->indexEvent('deposit_completed', [
            'user_id' => $deposit->user_id,
            'uid' => $user?->uid,
            'order_no' => $deposit->order_no,
            'amount' => (float) $deposit->amount,
            'currency' => $deposit->currency,
            'agent_id' => $agentId,
            'agent_link_id' => $user?->agent_link_id,
            'status' => $deposit->status,
            '@timestamp' => $timestamp,
            'source' => 'event',
        ], 'deposit_' . $deposit->id);

        if (!$result['success']) {
            Log::warning('OpenSearch index deposit_completed failed', [
                'deposit_id' => $deposit->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }
    }
}
