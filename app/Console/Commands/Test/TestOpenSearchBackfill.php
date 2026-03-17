<?php

namespace App\Console\Commands\Test;

use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\OpenSearchService;
use Illuminate\Console\Command;

class TestOpenSearchBackfill extends Command
{
    protected $signature = 'test:opensearch-backfill
                            {--users : 仅上传 users}
                            {--deposits : 仅上传 deposits}
                            {--withdraws : 仅上传 withdraws}
                            {--chunk=500 : 每批数量}
                            {--create-templates : 先创建模版}';

    protected $description = '将 users、deposits、withdraws 数据上传到 OpenSearch（回填）';

    public function handle(): int
    {
        $openSearch = new OpenSearchService();

        if (!$openSearch->isEnabled()) {
            $this->error('OpenSearch 未启用');
            return 1;
        }

        if (!$openSearch->ping()) {
            $this->error('OpenSearch 连接失败');
            return 1;
        }

        $onlyUsers = $this->option('users');
        $onlyDeposits = $this->option('deposits');
        $onlyWithdraws = $this->option('withdraws');

        if (!$onlyUsers && !$onlyDeposits && !$onlyWithdraws) {
            $onlyUsers = $onlyDeposits = $onlyWithdraws = true;
        }

        if ($this->option('create-templates')) {
            $r = $openSearch->applyIndexTemplates();
            if ($r['success']) {
                $this->info('✓ 模版已创建');
            } else {
                foreach ($r['errors'] ?? [] as $e) {
                    $this->warn($e);
                }
            }
            $this->newLine();
        }

        $chunkSize = (int) $this->option('chunk');
        $totalIndexed = 0;

        if ($onlyUsers) {
            $n = $this->backfillUsers($openSearch, $chunkSize);
            $totalIndexed += $n;
        }

        if ($onlyDeposits) {
            $n = $this->backfillDeposits($openSearch, $chunkSize);
            $totalIndexed += $n;
        }

        if ($onlyWithdraws) {
            $n = $this->backfillWithdraws($openSearch, $chunkSize);
            $totalIndexed += $n;
        }

        $this->newLine();
        $this->info("✓ 回填完成，共上传 {$totalIndexed} 条");
        return 0;
    }

    protected function backfillUsers(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('user_registered');
        $total = 0;

        $this->info('上传 users → ' . $index);

        User::query()
            ->with('agentLink')
            ->chunk($chunkSize, function ($users) use ($openSearch, $index, &$total) {
                $docs = [];
                foreach ($users as $user) {
                    $agentId = $user->agentLink?->agent_id ?? null;
                    $docs[] = [
                        'id' => 'user_' . $user->id,
                        'body' => [
                            'event_type' => 'user_registered',
                            '@timestamp' => $user->created_at?->toIso8601String() ?? now()->toIso8601String(),
                            'user_id' => $user->id,
                            'uid' => $user->uid,
                            'email' => $user->email,
                            'agent_id' => $agentId,
                            'agent_link_id' => $user->agent_link_id,
                            'source' => 'backfill',
                        ],
                    ];
                }
                $result = $openSearch->bulkIndex($index, $docs);
                $total += $result['indexed'];
                $this->line("  users: +{$result['indexed']} (累计 {$total})");
            });

        return $total;
    }

    protected function backfillDeposits(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('deposit_completed');
        $total = 0;

        $this->info('上传 deposits → ' . $index);

        Deposit::query()
            ->with('user.agentLink')
            ->chunk($chunkSize, function ($deposits) use ($openSearch, $index, &$total) {
                $docs = [];
                foreach ($deposits as $deposit) {
                    $user = $deposit->user;
                    $agentId = $user?->agentLink?->agent_id ?? null;
                    $eventType = $deposit->status === Deposit::STATUS_COMPLETED ? 'deposit_completed' : 'deposit_created';
                    $docs[] = [
                        'id' => 'deposit_' . $deposit->id,
                        'body' => [
                            'event_type' => $eventType,
                            '@timestamp' => ($deposit->completed_at ?? $deposit->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                            'user_id' => $deposit->user_id,
                            'uid' => $user?->uid,
                            'order_no' => $deposit->order_no,
                            'amount' => (float) $deposit->amount,
                            'currency' => $deposit->currency,
                            'agent_id' => $agentId,
                            'agent_link_id' => $user?->agent_link_id,
                            'status' => $deposit->status,
                            'source' => 'backfill',
                        ],
                    ];
                }
                $result = $openSearch->bulkIndex($index, $docs);
                $total += $result['indexed'];
                $this->line("  deposits: +{$result['indexed']} (累计 {$total})");
            });

        return $total;
    }

    protected function backfillWithdraws(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('withdraw_completed');
        $total = 0;

        $this->info('上传 withdraws → ' . $index);

        Withdraw::query()
            ->with('user.agentLink')
            ->chunk($chunkSize, function ($withdraws) use ($openSearch, $index, &$total) {
                $docs = [];
                foreach ($withdraws as $withdraw) {
                    $user = $withdraw->user;
                    $agentId = $user?->agentLink?->agent_id ?? null;
                    $eventType = $withdraw->status === Withdraw::STATUS_COMPLETED ? 'withdraw_completed' : 'withdraw_created';
                    $docs[] = [
                        'id' => 'withdraw_' . $withdraw->id,
                        'body' => [
                            'event_type' => $eventType,
                            '@timestamp' => ($withdraw->completed_at ?? $withdraw->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                            'user_id' => $withdraw->user_id,
                            'uid' => $user?->uid,
                            'order_no' => $withdraw->order_no,
                            'amount' => (float) $withdraw->amount,
                            'currency' => $withdraw->currency,
                            'agent_id' => $agentId,
                            'agent_link_id' => $user?->agent_link_id,
                            'status' => $withdraw->status,
                            'source' => 'backfill',
                        ],
                    ];
                }
                $result = $openSearch->bulkIndex($index, $docs);
                $total += $result['indexed'];
                $this->line("  withdraws: +{$result['indexed']} (累计 {$total})");
            });

        return $total;
    }

}
