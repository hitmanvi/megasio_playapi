<?php

namespace App\Console\Commands\Setting;

use App\Models\Setting;
use Illuminate\Console\Command;

class AddPromotionGroupSetting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setting:promotion-group:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize promotion group settings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Initializing promotion group settings...');

        // 定义要初始化的设置项
        $settings = [
            [
                'key' => 'welcome_bonus',
                'value' => [
                    'method' => 'fixed',
                    'amount' => 20
                ],
                'type' => 'json',
            ],
        ];

        if (empty($settings)) {
            $this->warn('No settings defined. Please add settings to the $settings array.');
            return Command::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($settings as $setting) {
            try {
                // 验证必需字段
                if (!isset($setting['key']) || !isset($setting['value'])) {
                    $this->warn("Skipping setting: missing key or value");
                    $skipped++;
                    continue;
                }

                $key = $setting['key'];
                $value = $setting['value'];
                $type = $setting['type'] ?? 'string';
                $description = $setting['description'] ?? null;

                // 检查是否已存在
                $existing = Setting::where('key', $key)->first();
                $isUpdate = $existing !== null;

                // 创建或更新设置
                Setting::setValue(
                    $key,
                    $value,
                    $type,
                    'promotion',
                    $description
                );

                if ($isUpdate) {
                    $updated++;
                    $this->line("Updated: {$key}");
                } else {
                    $created++;
                    $this->line("Created: {$key}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to process setting '{$setting['key']}': " . $e->getMessage());
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Initialization complete!");
        $this->info("Created: {$created}, Updated: {$updated}, Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
