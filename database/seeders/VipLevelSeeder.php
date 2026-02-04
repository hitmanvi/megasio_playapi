<?php

namespace Database\Seeders;

use App\Models\VipLevel;
use App\Models\VipLevelGroup;
use Illuminate\Database\Seeder;

class VipLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建VIP等级组
        $groups = [
            [
                'name' => 'Bronze',
                'icon' => 'vip-bronze',
                'card_img' => 'vip-bronze-card',
                'sort_id' => 1,
                'enabled' => true,
            ],
            [
                'name' => 'Silver',
                'icon' => 'vip-silver',
                'card_img' => 'vip-silver-card',
                'sort_id' => 2,
                'enabled' => true,
            ],
            [
                'name' => 'Gold',
                'icon' => 'vip-gold',
                'card_img' => 'vip-gold-card',
                'sort_id' => 3,
                'enabled' => true,
            ],
            [
                'name' => 'Platinum',
                'icon' => 'vip-platinum',
                'card_img' => 'vip-platinum-card',
                'sort_id' => 4,
                'enabled' => true,
            ],
            [
                'name' => 'Diamond',
                'icon' => 'vip-diamond',
                'card_img' => 'vip-diamond-card',
                'sort_id' => 5,
                'enabled' => true,
            ],
        ];

        $groupMap = [];
        foreach ($groups as $groupData) {
            $group = VipLevelGroup::updateOrCreate(
                ['name' => $groupData['name']],
                $groupData
            );
            $groupMap[$groupData['name']] = $group->id;
        }

        // 创建VIP等级，每个等级对应一个组
        $levels = [
            [
                'level' => '1',
                'group_id' => $groupMap['Bronze'],
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
                'group_id' => $groupMap['Silver'],
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
                'group_id' => $groupMap['Gold'],
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
                'group_id' => $groupMap['Platinum'],
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
                'group_id' => $groupMap['Diamond'],
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

