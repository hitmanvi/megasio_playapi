<?php

namespace App\Listeners;

use App\Events\WithdrawCompleted;
use App\Services\OpenSearchService;
use Illuminate\Support\Facades\Log;

class IndexWithdrawCompletedToOpenSearch
{
    public function handle(WithdrawCompleted $event): void
    {
        $service = new OpenSearchService();
        if (!$service->isEnabled()) {
            return;
        }

        $withdraw = $event->withdraw;
        $withdraw->loadMissing('user.agentLink');
        $user = $withdraw->user;
        $agentId = $user?->agentLink?->agent_id ?? null;

        $result = $service->indexEvent('withdraw_completed', [
            'user_id' => $withdraw->user_id,
            'uid' => $user?->uid,
            'order_no' => $withdraw->order_no,
            'amount' => (float) $withdraw->amount,
            'currency' => $withdraw->currency,
            'agent_id' => $agentId,
            'agent_link_id' => $user?->agent_link_id,
            'status' => $withdraw->status,
            '@timestamp' => ($withdraw->completed_at ?? $withdraw->created_at)?->toIso8601String() ?? now()->toIso8601String(),
            'source' => 'event',
        ], 'withdraw_' . $withdraw->id);

        if (!$result['success']) {
            Log::warning('OpenSearch index withdraw_completed failed', [
                'withdraw_id' => $withdraw->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }
    }
}
