<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $themes = [
            [
                'icon' => 'fas fa-fist-raised',
                'enabled' => true,
                'sort_id' => 1,
                'translations' => [
                    'en' => 'Action',
                    'zh-CN' => '动作',
                    'ja' => 'アクション',
                    'ko' => '액션',
                ],
            ],
            [
                'icon' => 'fas fa-map-marked-alt',
                'enabled' => true,
                'sort_id' => 2,
                'translations' => [
                    'en' => 'Adventure',
                    'zh-CN' => '冒险',
                    'ja' => 'アドベンチャー',
                    'ko' => '모험',
                ],
            ],
            [
                'icon' => 'fas fa-laugh',
                'enabled' => true,
                'sort_id' => 3,
                'translations' => [
                    'en' => 'Comedy',
                    'zh-CN' => '喜剧',
                    'ja' => 'コメディ',
                    'ko' => '코미디',
                ],
            ],
            [
                'icon' => 'fas fa-theater-masks',
                'enabled' => true,
                'sort_id' => 4,
                'translations' => [
                    'en' => 'Drama',
                    'zh-CN' => '剧情',
                    'ja' => 'ドラマ',
                    'ko' => '드라마',
                ],
            ],
            [
                'icon' => 'fas fa-ghost',
                'enabled' => true,
                'sort_id' => 5,
                'translations' => [
                    'en' => 'Horror',
                    'zh-CN' => '恐怖',
                    'ja' => 'ホラー',
                    'ko' => '공포',
                ],
            ],
            [
                'icon' => 'fas fa-heart',
                'enabled' => true,
                'sort_id' => 6,
                'translations' => [
                    'en' => 'Romance',
                    'zh-CN' => '爱情',
                    'ja' => 'ロマンス',
                    'ko' => '로맨스',
                ],
            ],
            [
                'icon' => 'fas fa-rocket',
                'enabled' => true,
                'sort_id' => 7,
                'translations' => [
                    'en' => 'Science Fiction',
                    'zh-CN' => '科幻',
                    'ja' => 'SF',
                    'ko' => 'SF',
                ],
            ],
            [
                'icon' => 'fas fa-exclamation-triangle',
                'enabled' => true,
                'sort_id' => 8,
                'translations' => [
                    'en' => 'Thriller',
                    'zh-CN' => '惊悚',
                    'ja' => 'スリラー',
                    'ko' => '스릴러',
                ],
            ],
        ];

        foreach ($themes as $themeData) {
            $translations = $themeData['translations'];
            unset($themeData['translations']);
            
            // Create the theme
            $theme = Theme::create($themeData);
            
            // Set translations
            $theme->setNames($translations);
        }
    }
}
