<?php

namespace App\Console\Commands\Test;

use App\Models\Deposit;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\Withdraw;
use App\Services\OpenSearchService;
use Illuminate\Console\Command;

class TestOpenSearchReplayEvents extends Command
{
    protected $signature = 'test:opensearch-replay-events
                            {--user-registered : 仅 user_registered}
                            {--user-logged-in : 仅 user_logged_in}
                            {--deposit-created : 仅 deposit_created}
                            {--deposit-completed : 仅 deposit_completed}
                            {--withdraw-completed : 仅 withdraw_completed}
                            {--first-deposit-completed : 仅 first_deposit_completed}
                            {--chunk=500 : 每批数量}
                            {--create-templates : 先创建模版}';

    protected $description = '按数据库已有数据重放事件到 OpenSearch（user_registered、user_logged_in、deposit_created、deposit_completed、withdraw_completed、first_deposit_completed）';

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

        $only = [
            'user_registered' => $this->option('user-registered'),
            'user_logged_in' => $this->option('user-logged-in'),
            'deposit_created' => $this->option('deposit-created'),
            'deposit_completed' => $this->option('deposit-completed'),
            'withdraw_completed' => $this->option('withdraw-completed'),
            'first_deposit_completed' => $this->option('first-deposit-completed'),
        ];

        if (!array_filter($only)) {
            $only = array_fill_keys(array_keys($only), true);
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

        if ($only['user_registered']) {
            $totalIndexed += $this->replayUserRegistered($openSearch, $chunkSize);
        }
        if ($only['user_logged_in']) {
            $totalIndexed += $this->replayUserLoggedIn($openSearch, $chunkSize);
        }
        if ($only['deposit_created']) {
            $totalIndexed += $this->replayDepositCreated($openSearch, $chunkSize);
        }
        if ($only['deposit_completed']) {
            $totalIndexed += $this->replayDepositCompleted($openSearch, $chunkSize);
        }
        if ($only['withdraw_completed']) {
            $totalIndexed += $this->replayWithdrawCompleted($openSearch, $chunkSize);
        }
        if ($only['first_deposit_completed']) {
            $totalIndexed += $this->replayFirstDepositCompleted($openSearch, $chunkSize);
        }

        $this->newLine();
        $this->info("✓ 重放完成，共上传 {$totalIndexed} 条");
        return 0;
    }

    protected function replayUserRegistered(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('user_registered');
        $total = 0;
        $this->info('重放 user_registered → ' . $index);

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
                            'source' => 'replay',
                        ],
                    ];
                }
                $result = $openSearch->bulkIndex($index, $docs);
                $total += $result['indexed'];
                $this->line("  user_registered: +{$result['indexed']} (累计 {$total})");
            });
        return $total;
    }

    protected function replayUserLoggedIn(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('user_logged_in');
        $total = 0;

        $loginCount = UserActivity::where('activity_type', UserActivity::TYPE_LOGIN)->count();
        if ($loginCount > 0) {
            $this->info("重放 user_logged_in（来自 user_activities）→ {$index}");

            UserActivity::where('activity_type', UserActivity::TYPE_LOGIN)
                ->with('user.agentLink')
                ->chunk($chunkSize, function ($activities) use ($openSearch, $index, &$total) {
                    $docs = [];
                    foreach ($activities as $i => $activity) {
                        $user = $activity->user;
                        if (!$user) {
                            continue;
                        }
                        $agentId = $user->agentLink?->agent_id ?? null;
                        $docs[] = [
                            'id' => 'login_' . $activity->id,
                            'body' => [
                                'event_type' => 'user_logged_in',
                                '@timestamp' => $activity->created_at?->toIso8601String() ?? now()->toIso8601String(),
                                'user_id' => $user->id,
                                'uid' => $user->uid,
                                'email' => $user->email,
                                'agent_id' => $agentId,
                                'agent_link_id' => $user->agent_link_id,
                                'source' => 'replay',
                            ],
                        ];
                    }
                    if (!empty($docs)) {
                        $result = $openSearch->bulkIndex($index, $docs);
                        $total += $result['indexed'];
                        $this->line("  user_logged_in: +{$result['indexed']} (累计 {$total})");
                    }
                });
        } else {
            $this->info("重放 user_logged_in（无登录记录，按用户各发一条）→ {$index}");

            User::query()
                ->with('agentLink')
                ->chunk($chunkSize, function ($users) use ($openSearch, $index, &$total) {
                    $docs = [];
                    foreach ($users as $user) {
                        $agentId = $user->agentLink?->agent_id ?? null;
                        $docs[] = [
                            'id' => 'login_user_' . $user->id,
                            'body' => [
                                'event_type' => 'user_logged_in',
                                '@timestamp' => $user->created_at?->toIso8601String() ?? now()->toIso8601String(),
                                'user_id' => $user->id,
                                'uid' => $user->uid,
                                'email' => $user->email,
                                'agent_id' => $agentId,
                                'agent_link_id' => $user->agent_link_id,
                                'source' => 'replay',
                            ],
                        ];
                    }
                    $result = $openSearch->bulkIndex($index, $docs);
                    $total += $result['indexed'];
                    $this->line("  user_logged_in: +{$result['indexed']} (累计 {$total})");
                });
        }
        return $total;
    }

    protected function replayDepositCreated(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('deposit_created');
        $total = 0;
        $this->info('重放 deposit_created → ' . $index);

        Deposit::query()
            ->where('status', '!=', Deposit::STATUS_COMPLETED)
            ->with('user.agentLink')
            ->chunk($chunkSize, function ($deposits) use ($openSearch, $index, &$total) {
                $docs = [];
                foreach ($deposits as $deposit) {
                    $user = $deposit->user;
                    $agentId = $user?->agentLink?->agent_id ?? null;
                    $docs[] = [
                        'id' => 'deposit_' . $deposit->id,
                        'body' => [
                            'event_type' => 'deposit_created',
                            '@timestamp' => $deposit->created_at?->toIso8601String() ?? now()->toIso8601String(),
                            'user_id' => $deposit->user_id,
                            'uid' => $user?->uid,
                            'order_no' => $deposit->order_no,
                            'amount' => (float) $deposit->amount,
                            'currency' => $deposit->currency,
                            'agent_id' => $agentId,
                            'agent_link_id' => $user?->agent_link_id,
                            'status' => $deposit->status,
                            'source' => 'replay',
                        ],
                    ];
                }
                if (!empty($docs)) {
                    $result = $openSearch->bulkIndex($index, $docs);
                    $total += $result['indexed'];
                    $this->line("  deposit_created: +{$result['indexed']} (累计 {$total})");
                }
            });
        return $total;
    }

    protected function replayDepositCompleted(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('deposit_completed');
        $total = 0;
        $this->info('重放 deposit_completed → ' . $index);

        Deposit::query()
            ->where('status', Deposit::STATUS_COMPLETED)
            ->with('user.agentLink')
            ->chunk($chunkSize, function ($deposits) use ($openSearch, $index, &$total) {
                $docs = [];
                foreach ($deposits as $deposit) {
                    $user = $deposit->user;
                    $agentId = $user?->agentLink?->agent_id ?? null;
                    $docs[] = [
                        'id' => 'deposit_' . $deposit->id,
                        'body' => [
                            'event_type' => 'deposit_completed',
                            '@timestamp' => ($deposit->completed_at ?? $deposit->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                            'user_id' => $deposit->user_id,
                            'uid' => $user?->uid,
                            'order_no' => $deposit->order_no,
                            'amount' => (float) $deposit->amount,
                            'currency' => $deposit->currency,
                            'agent_id' => $agentId,
                            'agent_link_id' => $user?->agent_link_id,
                            'status' => $deposit->status,
                            'source' => 'replay',
                        ],
                    ];
                }
                if (!empty($docs)) {
                    $result = $openSearch->bulkIndex($index, $docs);
                    $total += $result['indexed'];
                    $this->line("  deposit_completed: +{$result['indexed']} (累计 {$total})");
                }
            });
        return $total;
    }

    protected function replayWithdrawCompleted(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('withdraw_completed');
        $total = 0;
        $this->info('重放 withdraw_completed → ' . $index);

        Withdraw::query()
            ->where('status', Withdraw::STATUS_COMPLETED)
            ->with('user.agentLink')
            ->chunk($chunkSize, function ($withdraws) use ($openSearch, $index, &$total) {
                $docs = [];
                foreach ($withdraws as $withdraw) {
                    $user = $withdraw->user;
                    $agentId = $user?->agentLink?->agent_id ?? null;
                    $docs[] = [
                        'id' => 'withdraw_' . $withdraw->id,
                        'body' => [
                            'event_type' => 'withdraw_completed',
                            '@timestamp' => ($withdraw->completed_at ?? $withdraw->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                            'user_id' => $withdraw->user_id,
                            'uid' => $user?->uid,
                            'order_no' => $withdraw->order_no,
                            'amount' => (float) $withdraw->amount,
                            'currency' => $withdraw->currency,
                            'agent_id' => $agentId,
                            'agent_link_id' => $user?->agent_link_id,
                            'status' => $withdraw->status,
                            'source' => 'replay',
                        ],
                    ];
                }
                if (!empty($docs)) {
                    $result = $openSearch->bulkIndex($index, $docs);
                    $total += $result['indexed'];
                    $this->line("  withdraw_completed: +{$result['indexed']} (累计 {$total})");
                }
            });
        return $total;
    }

    protected function replayFirstDepositCompleted(OpenSearchService $openSearch, int $chunkSize): int
    {
        $index = $openSearch->getIndexForEvent('first_deposit_completed');
        $total = 0;
        $this->info('重放 first_deposit_completed → ' . $index);

        $firstDeposits = Deposit::query()
            ->where('status', Deposit::STATUS_COMPLETED)
            ->with('user.agentLink')
            ->orderBy('user_id')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get()
            ->unique('user_id');

        $docs = [];
        foreach ($firstDeposits as $deposit) {
            $user = $deposit->user;
            $agentId = $user?->agentLink?->agent_id ?? null;
            $docs[] = [
                'id' => 'first_deposit_' . $deposit->id,
                'body' => [
                    'event_type' => 'first_deposit_completed',
                    '@timestamp' => ($deposit->completed_at ?? $deposit->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                    'user_id' => $deposit->user_id,
                    'uid' => $user?->uid,
                    'order_no' => $deposit->order_no,
                    'amount' => (float) $deposit->amount,
                    'currency' => $deposit->currency,
                    'agent_id' => $agentId,
                    'agent_link_id' => $user?->agent_link_id,
                    'status' => $deposit->status,
                    'source' => 'replay',
                ],
            ];
        }

        if (!empty($docs)) {
            foreach (array_chunk($docs, $chunkSize) as $chunk) {
                $result = $openSearch->bulkIndex($index, $chunk);
                $total += $result['indexed'];
            }
            $this->line("  first_deposit_completed: +{$total} (共 {$total})");
        }
        return $total;
    }
}
