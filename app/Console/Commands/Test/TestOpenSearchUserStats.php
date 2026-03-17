<?php

namespace App\Console\Commands\Test;

use App\Services\OpenSearchService;
use Illuminate\Console\Command;

class TestOpenSearchUserStats extends Command
{
    protected $signature = 'test:opensearch-user-stats
                            {--size=100 : 返回用户数上限}';

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
        $this->info("获取用户充提汇总（size={$size}）...");

        $result = $openSearch->getUserDepositWithdrawTotals(['size' => $size]);

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
