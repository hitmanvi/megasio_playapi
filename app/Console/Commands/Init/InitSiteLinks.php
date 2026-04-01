<?php

namespace App\Console\Commands\Init;

use App\Models\SiteLink;
use Illuminate\Console\Command;

class InitSiteLinks extends Command
{
    protected $signature = 'init:site-links';

    protected $description = '初始化站点固定链接 key（不可删除，url 可在后台配置）';

    /** 系统固定 key，不可删除 */
    public const PROTECTED_KEYS = [
        'privacy_policy',
        'terms_of_service',
        'user_agreement',
        'bonus_terms',
        'aml',
        'check_in_rules',
        'commission_rules',
        'contact_us',
        'refer_rules',
    ];

    public function handle(): int
    {
        $this->info('初始化 site_links 固定项...');

        $created = 0;
        foreach (self::PROTECTED_KEYS as $key) {
            $link = SiteLink::query()->where('key', $key)->first();
            if ($link) {
                $this->line("  已存在: {$key}");

                continue;
            }
            SiteLink::query()->create([
                'key' => $key,
                'url' => '',
                'deletable' => false,
            ]);
            $this->info("  已创建: {$key}");
            $created++;
        }

        $this->newLine();
        $this->info('✓ 完成（新建 '.$created.' 条，共 '.count(self::PROTECTED_KEYS).' 个固定 key）');

        return Command::SUCCESS;
    }
}
