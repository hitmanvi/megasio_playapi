<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // App 设置
            [
                'key' => 'supported_locales',
                'value' => json_encode(['en', 'zh-CN', 'ja', 'ko']),
                'type' => 'array',
                'group' => 'app',
                'description' => '支持的语言列表',
            ],
            [
                'key' => 'app_limit',
                'value' => '10',
                'type' => 'integer',
                'group' => 'app',
                'description' => 'APP 端分页限制',
            ],
            [
                'key' => 'web_limit',
                'value' => '10',
                'type' => 'integer',
                'group' => 'app',
                'description' => 'Web 端分页限制',
            ],
            [
                'key' => 'app_version',
                'value' => '1.0.0',
                'type' => 'string',
                'group' => 'app',
                'description' => 'APP 版本号',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}

