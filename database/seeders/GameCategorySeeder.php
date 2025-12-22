<?php

namespace Database\Seeders;

use App\Models\GameCategory;
use Illuminate\Database\Seeder;

class GameCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'icon' => 'fas fa-film',
                'enabled' => true,
                'sort_id' => 1,
                'translations' => [
                    'en' => 'Movie',
                    'zh-CN' => '电影',
                    'ja' => '映画',
                    'ko' => '영화',
                ],
            ],
            [
                'icon' => 'fas fa-tv',
                'enabled' => true,
                'sort_id' => 2,
                'translations' => [
                    'en' => 'TV Series',
                    'zh-CN' => '电视剧',
                    'ja' => 'テレビドラマ',
                    'ko' => 'TV 시리즈',
                ],
            ],
            [
                'icon' => 'fas fa-user-ninja',
                'enabled' => true,
                'sort_id' => 3,
                'translations' => [
                    'en' => 'Anime',
                    'zh-CN' => '动漫',
                    'ja' => 'アニメ',
                    'ko' => '애니메이션',
                ],
            ],
            [
                'icon' => 'fas fa-book-open',
                'enabled' => true,
                'sort_id' => 4,
                'translations' => [
                    'en' => 'Documentary',
                    'zh-CN' => '纪录片',
                    'ja' => 'ドキュメンタリー',
                    'ko' => '다큐멘터리',
                ],
            ],
            [
                'icon' => 'fas fa-clock',
                'enabled' => true,
                'sort_id' => 5,
                'translations' => [
                    'en' => 'Short Film',
                    'zh-CN' => '短片',
                    'ja' => '短編映画',
                    'ko' => '단편 영화',
                ],
            ],
            [
                'icon' => 'fas fa-globe',
                'enabled' => true,
                'sort_id' => 6,
                'translations' => [
                    'en' => 'Web Series',
                    'zh-CN' => '网络剧',
                    'ja' => 'ウェブシリーズ',
                    'ko' => '웹 시리즈',
                ],
            ],
        ];

        foreach ($categories as $data) {
            $translations = $data['translations'];
            unset($data['translations']);

            // Set name field with English name as default
            $data['name'] = $translations['en'] ?? '';

            // Create the game category
            $category = GameCategory::create($data);

            // Set translations
            $category->setNames($translations);
        }
    }
}
