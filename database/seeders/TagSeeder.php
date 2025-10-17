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
            // Theme tags
            [
                'name' => 'action',
                'type' => 'theme',
                'translations' => [
                    'en' => 'Action',
                    'zh-CN' => '动作',
                    'ja' => 'アクション',
                    'ko' => '액션',
                ]
            ],
            [
                'name' => 'adventure',
                'type' => 'theme',
                'translations' => [
                    'en' => 'Adventure',
                    'zh-CN' => '冒险',
                    'ja' => 'アドベンチャー',
                    'ko' => '모험',
                ]
            ],
            [
                'name' => 'comedy',
                'type' => 'theme',
                'translations' => [
                    'en' => 'Comedy',
                    'zh-CN' => '喜剧',
                    'ja' => 'コメディ',
                    'ko' => '코미디',
                ]
            ],
            [
                'name' => 'drama',
                'type' => 'theme',
                'translations' => [
                    'en' => 'Drama',
                    'zh-CN' => '剧情',
                    'ja' => 'ドラマ',
                    'ko' => '드라마',
                ]
            ],
            [
                'name' => 'horror',
                'type' => 'theme',
                'translations' => [
                    'en' => 'Horror',
                    'zh-CN' => '恐怖',
                    'ja' => 'ホラー',
                    'ko' => '공포',
                ]
            ],
            [
                'name' => 'romance',
                'type' => 'theme',
                'translations' => [
                    'en' => 'Romance',
                    'zh-CN' => '爱情',
                    'ja' => 'ロマンス',
                    'ko' => '로맨스',
                ]
            ],
            [
                'name' => 'sci-fi',
                'type' => 'theme',
                'translations' => [
                    'en' => 'Science Fiction',
                    'zh-CN' => '科幻',
                    'ja' => 'SF',
                    'ko' => 'SF',
                ]
            ],
            [
                'name' => 'thriller',
                'type' => 'theme',
                'translations' => [
                    'en' => 'Thriller',
                    'zh-CN' => '惊悚',
                    'ja' => 'スリラー',
                    'ko' => '스릴러',
                ]
            ],

            // Category tags
            [
                'name' => 'movie',
                'type' => 'category',
                'translations' => [
                    'en' => 'Movie',
                    'zh-CN' => '电影',
                    'ja' => '映画',
                    'ko' => '영화',
                ]
            ],
            [
                'name' => 'tv-series',
                'type' => 'category',
                'translations' => [
                    'en' => 'TV Series',
                    'zh-CN' => '电视剧',
                    'ja' => 'テレビドラマ',
                    'ko' => 'TV 시리즈',
                ]
            ],
            [
                'name' => 'anime',
                'type' => 'category',
                'translations' => [
                    'en' => 'Anime',
                    'zh-CN' => '动漫',
                    'ja' => 'アニメ',
                    'ko' => '애니메이션',
                ]
            ],
            [
                'name' => 'documentary',
                'type' => 'category',
                'translations' => [
                    'en' => 'Documentary',
                    'zh-CN' => '纪录片',
                    'ja' => 'ドキュメンタリー',
                    'ko' => '다큐멘터리',
                ]
            ],
            [
                'name' => 'short-film',
                'type' => 'category',
                'translations' => [
                    'en' => 'Short Film',
                    'zh-CN' => '短片',
                    'ja' => '短編映画',
                    'ko' => '단편 영화',
                ]
            ],
            [
                'name' => 'web-series',
                'type' => 'category',
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
