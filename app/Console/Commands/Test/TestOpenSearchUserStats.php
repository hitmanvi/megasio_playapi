<?php

namespace App\Console\Commands\Test;

use App\Services\OpenSearchService;
use Illuminate\Console\Command;

class TestOpenSearchUserStats extends Command
{
    protected $signature = 'test:opensearch-user-stats
                            {--size=100 : 返回用户数上限}
                            {--uid= : 按用户 uid 过滤}
                            {--date-from= : 充提日期起（Y-m-d）}
                            {--date-to= : 充提日期止（Y-m-d）}
                            {--agent-id= : 按 agent_id 过滤}
                            {--agent-link-id= : 按 agent_link_id 过滤}
                            {--timezone=UTC : 时区，如 UTC+8、UTC-4}';

    protected $description = '测试从 OpenSearch 获取用户充提金额汇总';

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

        $size = (int) $this->option('size');
        $options = ['size' => $size, 'timezone' => $this->option('timezone')];

        if ($this->option('uid')) {
            $options['uid'] = $this->option('uid');
        }
        if ($this->option('date-from')) {
            $options['date_from'] = $this->option('date-from');
        }
        if ($this->option('date-to')) {
            $options['date_to'] = $this->option('date-to');
        }
        if ($this->option('agent-id') !== null && $this->option('agent-id') !== '') {
            $options['agent_id'] = (int) $this->option('agent-id');
        }
        if ($this->option('agent-link-id') !== null && $this->option('agent-link-id') !== '') {
            $options['agent_link_id'] = (int) $this->option('agent-link-id');
        }

        $filterDesc = $options['timezone'] !== 'UTC' ? " 时区={$options['timezone']}" : '';
        if (!empty($options['uid'])) {
            $filterDesc .= " uid={$options['uid']}";
        }
        if (!empty($options['date_from'])) {
            $filterDesc .= " 日期 {$options['date_from']}";
        }
        if (!empty($options['date_to'])) {
            $filterDesc .= "~{$options['date_to']}";
        }
        if (isset($options['agent_id'])) {
            $filterDesc .= " agent_id={$options['agent_id']}";
        }
        if (isset($options['agent_link_id'])) {
            $filterDesc .= " agent_link_id={$options['agent_link_id']}";
        }
        $this->info("获取用户充提汇总（size={$size}{$filterDesc}）...");

        $result = $openSearch->getUserDepositWithdrawTotals($options);

        if (!$result['success']) {
            $this->error('获取失败: ' . ($result['error'] ?? 'unknown'));
            return 1;
        }

        $data = $result['data'] ?? [];
        $count = count($data);

        if ($count === 0) {
            $this->warn('暂无数据（可能尚未回填 deposits/withdraws）');
            return 0;
        }

        $this->info("✓ 共 {$count} 个用户");

        $rows = array_map(fn ($row) => [
            $row['user_id'],
            number_format($row['deposit_total'], 2),
            number_format($row['withdraw_total'], 2),
            number_format($row['deposit_completed_total'], 2),
            number_format($row['withdraw_completed_total'], 2),
        ], $data);

        $this->table(
            ['user_id', '充值总额', '提现总额', '成功充值总额', '成功提现总额'],
            $rows
        );

        return 0;
    }
}
