<?php

namespace App\Console\Commands\Init;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Console\Command;

class InitSetting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'init:setting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize settings from config/setting.php';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Initializing settings from config/setting.php...');

        // 读取配置文件
        $settings = config('setting');

        if (empty($settings) || !is_array($settings)) {
            $this->error('No settings found in config/setting.php');
            return Command::FAILURE;
        }

        $settingService = new SettingService();
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $tableData = [];

        foreach ($settings as $settingData) {
            try {
                $key = $settingData['key'] ?? null;
                $group = $settingData['group'] ?? null;
                $value = $settingData['value'] ?? null;

                if (!$key || !$group) {
                    $this->warn("Skipping setting with missing key or group");
                    $errorCount++;
                    continue;
                }

                // 检查是否已存在（根据 key 和 group）
                $existing = Setting::where('key', $key)
                    ->where('group', $group)
                    ->first();

                if ($existing) {
                    $this->line("Setting '{$key}' (group: {$group}) already exists, skipping...");
                    $skippedCount++;
                    continue;
                }

                // 确定 type（如果 value 是数组，使用 json）
                $type = 'json';
                if (is_string($value)) {
                    $type = 'string';
                } elseif (is_int($value)) {
                    $type = 'integer';
                } elseif (is_bool($value)) {
                    $type = 'boolean';
                } elseif (is_float($value)) {
                    $type = 'float';
                } elseif (is_array($value)) {
                    $type = 'json';
                }

                // 准备存储的值
                $storedValue = $settingService->prepareValue($value, $type);

                // 创建设置
                Setting::create([
                    'key' => $key,
                    'value' => $storedValue,
                    'type' => $type,
                    'group' => $group,
                    'description' => $settingData['description'] ?? null,
                ]);

                $createdCount++;
                $tableData[] = [
                    $key,
                    $group,
                    $type,
                    is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
                ];

                $this->info("Created setting '{$key}' (group: {$group})");
            } catch (\Exception $e) {
                $this->error("Failed to process setting: " . $e->getMessage());
                $errorCount++;
            }
        }

        // 显示结果表格
        if (!empty($tableData)) {
            $this->table(
                ['Key', 'Group', 'Type', 'Value'],
                $tableData
            );
        }

        // 显示统计信息
        $this->info("\nSettings initialization complete:");
        $this->info("Created: {$createdCount}");
        $this->info("Skipped: {$skippedCount}");
        if ($errorCount > 0) {
            $this->warn("Errors: {$errorCount}");
        }

        return Command::SUCCESS;
    }
}
