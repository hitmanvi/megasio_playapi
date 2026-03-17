<?php

namespace App\Console\Commands;

use App\Services\OpenSearchService;
use Illuminate\Console\Command;

class OpenSearchInitTemplates extends Command
{
    protected $signature = 'opensearch:init-templates';

    protected $description = '应用 OpenSearch index 模版（建议首次使用或修改模版后执行）';

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

        $this->info('应用 index 模版...');
        $result = $openSearch->applyIndexTemplates();

        if ($result['success']) {
            $this->info('✓ 模版应用成功: ' . implode(', ', $result['applied']));
            return 0;
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
