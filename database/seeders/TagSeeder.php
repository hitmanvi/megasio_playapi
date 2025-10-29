<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            // Category tags (themes are now in separate themes table)
            [
                'name' => 'movie',
                'icon' => 'fas fa-film',
                'enabled' => true,
                'translations' => [
                    'en' => 'Movie',
                    'zh-CN' => '电影',
                    'ja' => '映画',
                    'ko' => '영화',
                ]
            ],
            [
                'name' => 'tv-series',
                'icon' => 'fas fa-tv',
                'enabled' => true,
                'translations' => [
                    'en' => 'TV Series',
                    'zh-CN' => '电视剧',
                    'ja' => 'テレビドラマ',
                    'ko' => 'TV 시리즈',
                ]
            ],
            [
                'name' => 'anime',
                'icon' => 'fas fa-user-ninja',
                'enabled' => true,
                'translations' => [
                    'en' => 'Anime',
                    'zh-CN' => '动漫',
                    'ja' => 'アニメ',
                    'ko' => '애니메이션',
                ]
            ],
            [
                'name' => 'documentary',
                'icon' => 'fas fa-book-open',
                'enabled' => true,
                'translations' => [
                    'en' => 'Documentary',
                    'zh-CN' => '纪录片',
                    'ja' => 'ドキュメンタリー',
                    'ko' => '다큐멘터리',
                ]
            ],
            [
                'name' => 'short-film',
                'icon' => 'fas fa-clock',
                'enabled' => true,
                'translations' => [
                    'en' => 'Short Film',
                    'zh-CN' => '短片',
                    'ja' => '短編映画',
                    'ko' => '단편 영화',
                ]
            ],
            [
                'name' => 'web-series',
                'icon' => 'fas fa-globe',
                'enabled' => true,
                'translations' => [
                    'en' => 'Web Series',
                    'zh-CN' => '网络剧',
                    'ja' => 'ウェブシリーズ',
                    'ko' => '웹 시리즈',
                ]
            ],
        ];

        foreach ($tags as $tagData) {
            $translations = $tagData['translations'];
            unset($tagData['translations']);

            // Create the tag
            $tag = Tag::create($tagData);

            // Set translations using the Translatable trait
            $tag->setNames($translations);
        }
    }
}
