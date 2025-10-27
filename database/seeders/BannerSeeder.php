<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banners = [
            [
                'type' => Banner::TYPE_HOME,
                'web_img_url' => 'https://example.com/banners/home-web.jpg',
                'app_img_url' => 'https://example.com/banners/home-app.jpg',
                'web_rule_url' => 'https://example.com/games',
                'app_rule_url' => 'app://games',
                'enabled' => true,
                'sort_id' => 1,
                'description' => '首页主Banner',
            ],
            [
                'type' => Banner::TYPE_HOME,
                'web_img_url' => 'https://example.com/banners/promotion-web.jpg',
                'app_img_url' => 'https://example.com/banners/promotion-app.jpg',
                'web_rule_url' => 'https://example.com/promotion',
                'app_rule_url' => 'app://promotion',
                'enabled' => true,
                'sort_id' => 2,
                'description' => '首页促销Banner',
            ],
            [
                'type' => Banner::TYPE_PROMOTION,
                'web_img_url' => 'https://example.com/banners/special-web.jpg',
                'app_img_url' => 'https://example.com/banners/special-app.jpg',
                'web_rule_url' => 'https://example.com/special',
                'app_rule_url' => 'app://special',
                'enabled' => true,
                'sort_id' => 1,
                'description' => '促销活动Banner',
            ],
            [
                'type' => Banner::TYPE_ADVERTISEMENT,
                'web_img_url' => 'https://example.com/banners/ad-web.jpg',
                'app_img_url' => 'https://example.com/banners/ad-app.jpg',
                'web_rule_url' => 'https://example.com/ad',
                'app_rule_url' => 'app://ad',
                'enabled' => true,
                'sort_id' => 1,
                'description' => '广告Banner',
            ],
            [
                'type' => Banner::TYPE_HOME,
                'web_img_url' => 'https://example.com/banners/disabled-web.jpg',
                'app_img_url' => 'https://example.com/banners/disabled-app.jpg',
                'web_rule_url' => 'https://example.com/disabled',
                'app_rule_url' => 'app://disabled',
                'enabled' => false,
                'sort_id' => 3,
                'description' => '已禁用的Banner',
            ],
        ];

        foreach ($banners as $banner) {
            Banner::create($banner);
        }
    }
}
