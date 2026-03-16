<?php

namespace App\Console\Commands\Test;

use App\Services\OpenSearchService;
use Illuminate\Console\Command;

class TestOpenSearchEvent extends Command
{
    protected $signature = 'test:opensearch-event
                            {--event=user_registered : 事件类型，如 user_registered}
                            {--user-id=1 : 用户 ID}
                            {--uid=TEST001 : 用户 UID}
                            {--email=test@example.com : 邮箱}
                            {--debug : 输出调试日志}
                            {--config : 仅输出配置信息}
                            {--get= : 取回文档：指定文档 ID 时执行取回而非上传}
                            {--search : 搜索最近文档（取回测试）}';

    protected $description = '测试 OpenSearch 事件上传（注册事件等）';

    public function handle(): int
    {
        $eventType = $this->option('event');
        $userId = (int) $this->option('user-id');
        $uid = $this->option('uid');
        $email = $this->option('email');

        if ($this->option('config')) {
            $this->outputConfig();
            return 0;
        }

        $getDocId = $this->option('get');
        if ($getDocId !== null && $getDocId !== '') {
            return $this->getDocument($eventType, $getDocId);
        }

        if ($this->option('search')) {
            return $this->searchDocuments($eventType);
        }

        if ($this->option('debug')) {
            config(['opensearch.debug' => true]);
            $this->outputConfig();
            $this->newLine();
            $this->line('Debug 模式已开启，详细日志输出到 ' . storage_path('logs/laravel.log'));
            $this->newLine();
        }

        $openSearch = new OpenSearchService();

        if (!$openSearch->isEnabled()) {
            $this->error('OpenSearch 未启用，请在 .env 中设置 OPENSEARCH_ENABLED=true 并配置 OPENSEARCH_HOSTS');
            return 1;
        }

        $this->info("测试事件: {$eventType}");
        $this->line("目标 index: {$openSearch->getIndexForEvent($eventType)}");
        $this->newLine();

        // 先 ping 检查连接
        if (!$openSearch->ping()) {
            $this->error('OpenSearch 连接失败，请检查 OPENSEARCH_HOSTS 及网络');
            return 1;
        }
        $this->line('✓ OpenSearch 连接正常');
        $this->newLine();

        $payload = $this->buildPayload($eventType, $userId, $uid, $email);
        $this->line('上传数据: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->newLine();

        $result = $openSearch->indexEvent($eventType, $payload);

        if ($result['success']) {
            $this->info('✓ 事件上传成功');
            $this->line('文档 ID: ' . ($result['id'] ?? 'N/A'));
            $this->line('取回测试: php artisan test:opensearch-event --get=' . ($result['id'] ?? ''));
            return 0;
        }

        $this->error('✗ 事件上传失败');
        $this->line('错误: ' . ($result['error'] ?? 'unknown'));
        return 1;
    }

    protected function getDocument(string $eventType, string $docId): int
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

        $index = $openSearch->getIndexForEvent($eventType);
        $this->info("取回文档: index={$index}, id={$docId}");
        $this->newLine();

        $result = $openSearch->getDocument($index, $docId);

        if (!$result['success']) {
            $this->error('✗ 取回失败: ' . ($result['error'] ?? 'unknown'));
            return 1;
        }

        if (!($result['found'] ?? false)) {
            $this->warn('文档不存在');
            return 1;
        }

        $this->info('✓ 取回成功');
        $this->line(json_encode($result['document'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return 0;
    }

    protected function searchDocuments(string $eventType): int
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

        $index = $openSearch->getIndexForEvent($eventType);
        $this->info("搜索文档: index={$index}, size=10");
        $this->newLine();

        $result = $openSearch->search($index, [], [
            'size' => 10,
            'sort' => [['@timestamp' => ['order' => 'desc']]],
        ]);

        if (!$result['success']) {
            $this->error('✗ 搜索失败: ' . ($result['error'] ?? 'unknown'));
            return 1;
        }

        $this->info("✓ 共 {$result['total']} 条，显示最近 10 条:");
        $this->newLine();

        foreach ($result['hits'] ?? [] as $doc) {
            $id = $doc['_id'] ?? '-';
            $source = $doc['_source'] ?? [];
            $this->line("--- _id: {$id} ---");
            $this->line(json_encode($source, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->newLine();
        }

        if (empty($result['hits'])) {
            $this->line('(无结果)');
        }

        return 0;
    }

    protected function outputConfig(): void
    {
        $hosts = config('opensearch.hosts', []);
        $username = config('opensearch.username');
        $hasAuth = !empty($username) && !empty(config('opensearch.password'));

        $this->info('OpenSearch 配置');
        $this->table(
            ['配置项', '值'],
            [
                ['enabled', config('opensearch.enabled') ? 'true' : 'false'],
                ['hosts', is_array($hosts) ? implode(', ', $hosts) : $hosts],
                ['username', $username ?: '(未设置)'],
                ['password', $hasAuth ? '****' : '(未设置)'],
                ['index_prefix', config('opensearch.index_prefix', 'playapi')],
                ['debug', config('opensearch.debug') ? 'true' : 'false'],
                ['connect_timeout', (string) config('opensearch.connect_timeout', 5)],
                ['request_timeout', (string) config('opensearch.request_timeout', 30)],
            ]
        );

        $this->newLine();
        $this->line('事件 → Index 映射:');
        foreach (config('opensearch.event_indices', []) as $event => $suffix) {
            $fullIndex = config('opensearch.index_prefix', 'playapi') . '-' . $suffix;
            $this->line("  {$event} → {$fullIndex}");
        }
    }

    protected function buildPayload(string $eventType, int $userId, string $uid, string $email): array
    {
        return match ($eventType) {
            'user_registered' => [
                'user_id' => $userId,
                'uid' => $uid,
                'email' => $email,
                'source' => 'test_command',
            ],
            default => [
                'user_id' => $userId,
                'uid' => $uid,
                'email' => $email,
                'source' => 'test_command',
            ],
        };
    }

}
