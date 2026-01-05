<?php

namespace Database\Seeders;

use App\Models\VipLevel;
use Illuminate\Database\Seeder;

class VipLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            [
                'level' => '1',
                'name' => 'Bronze',
                'icon' => 'vip-bronze',
                'required_exp' => 0,
                'description' => '初始等级，开启VIP之旅',
                'benefits' => [
                    'daily_bonus' => 100,
                    'withdraw_limit' => 1000,
                    'cashback_rate' => 0.01,
                ],
                'sort_id' => 1,
                'enabled' => true,
            ],
            [
                'level' => '2',
                'name' => 'Silver',
                'icon' => 'vip-silver',
                'required_exp' => 500,
                'description' => '累计500经验值可达此等级',
                'benefits' => [
                    'daily_bonus' => 200,
                    'withdraw_limit' => 3000,
                    'cashback_rate' => 0.02,
                ],
                'sort_id' => 2,
                'enabled' => true,
            ],
            [
                'level' => '3',
                'name' => 'Gold',
                'icon' => 'vip-gold',
                'required_exp' => 2000,
                'description' => '累计2000经验值可达此等级',
                'benefits' => [
                    'daily_bonus' => 500,
                    'withdraw_limit' => 5000,
                    'cashback_rate' => 0.03,
                    'exclusive_games' => true,
                ],
                'sort_id' => 3,
                'enabled' => true,
            ],
            [
                'level' => '4',
                'name' => 'Platinum',
                'icon' => 'vip-platinum',
                'required_exp' => 5000,
                'description' => '累计5000经验值可达此等级',
                'benefits' => [
                    'daily_bonus' => 1000,
                    'withdraw_limit' => 10000,
                    'cashback_rate' => 0.05,
                    'exclusive_games' => true,
                    'priority_support' => true,
                ],
                'sort_id' => 4,
                'enabled' => true,
            ],
            [
                'level' => '5',
                'name' => 'Diamond',
                'icon' => 'vip-diamond',
                'required_exp' => 10000,
                'description' => '最高等级，尊享全部VIP特权',
                'benefits' => [
                    'daily_bonus' => 2000,
                    'withdraw_limit' => 50000,
                    'cashback_rate' => 0.1,
                    'exclusive_games' => true,
                    'priority_support' => true,
                    'personal_manager' => true,
                ],
                'sort_id' => 5,
                'enabled' => true,
            ],
        ];

        foreach ($levels as $level) {
            VipLevel::updateOrCreate(
                ['level' => $level['level']],
                $level
            );
        }

        // 清除缓存
        VipLevel::clearCache();
    }
}

