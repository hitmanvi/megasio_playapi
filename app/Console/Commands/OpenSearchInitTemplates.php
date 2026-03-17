<?php

namespace App\Console\Commands;

use App\Services\OpenSearchService;
use Illuminate\Console\Command;

class OpenSearchInitTemplates extends Command
{
    protected $signature = 'opensearch:create-templates
                            {--name= : 仅创建指定模版，不传则创建全部}';

    protected $description = '创建 OpenSearch index 模版（根据 config/opensearch.php 的 index_templates 配置）';

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

        $onlyName = $this->option('name');
        $this->info($onlyName ? "创建模版: {$onlyName}" : '创建 index 模版...');
        $result = $openSearch->applyIndexTemplates($onlyName ?: null);

        if ($result['success']) {
            if (empty($result['applied'])) {
                $this->warn('未找到匹配的模版' . ($onlyName ? " (--name={$onlyName})" : ''));
            } else {
                $this->info('✓ 模版创建成功: ' . implode(', ', $result['applied']));
            }
            return empty($result['errors']) ? 0 : 1;
        }

        foreach ($result['errors'] as $err) {
            $this->error($err);
        }
        if (!empty($result['applied'])) {
            $this->line('部分成功: ' . implode(', ', $result['applied']));
        }
        return 1;
    }

}
