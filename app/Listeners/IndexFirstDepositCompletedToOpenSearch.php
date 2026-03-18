<?php

namespace App\Listeners;

use App\Events\FirstDepositCompleted;
use App\Services\OpenSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class IndexFirstDepositCompletedToOpenSearch implements ShouldQueue
{
    use InteractsWithQueue;
    public function handle(FirstDepositCompleted $event): void
    {
        $service = new OpenSearchService();
        if (!$service->isEnabled()) {
            return;
        }

        $deposit = $event->deposit;
        $deposit->loadMissing('user.agentLink');
        $user = $deposit->user;
        $agentId = $user?->agentLink?->agent_id ?? null;

        $result = $service->indexEvent('first_deposit_completed', [
            'user_id' => $deposit->user_id,
            'uid' => $user?->uid,
            'order_no' => $deposit->order_no,
            'amount' => (float) $deposit->amount,
            'currency' => $deposit->currency,
            'agent_id' => $agentId,
            'agent_link_id' => $user?->agent_link_id,
            'status' => $deposit->status,
            '@timestamp' => ($deposit->completed_at ?? $deposit->created_at)?->toIso8601String() ?? now()->toIso8601String(),
            'source' => 'event',
        ], 'first_deposit_' . $deposit->id);

        if (!$result['success']) {
            Log::warning('OpenSearch index first_deposit_completed failed', [
                'deposit_id' => $deposit->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }
    }
}
