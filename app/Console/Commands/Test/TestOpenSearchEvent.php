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
                            {--config : 仅输出配置信息}';

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
            return 0;
        }

        $this->error('✗ 事件上传失败');
        $this->line('错误: ' . ($result['error'] ?? 'unknown'));
        return 1;
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
