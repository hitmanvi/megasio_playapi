<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample standalone translations for common UI elements
        $translations = [
            // Common UI elements
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 1,
                'field' => 'title',
                'locale' => 'en',
                'value' => 'Welcome to PlayAPI',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 1,
                'field' => 'title',
                'locale' => 'zh-CN',
                'value' => '欢迎使用 PlayAPI',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 1,
                'field' => 'title',
                'locale' => 'ja',
                'value' => 'PlayAPI へようこそ',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 1,
                'field' => 'title',
                'locale' => 'ko',
                'value' => 'PlayAPI에 오신 것을 환영합니다',
            ],

            // Navigation menu items
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 2,
                'field' => 'name',
                'locale' => 'en',
                'value' => 'Home',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 2,
                'field' => 'name',
                'locale' => 'zh-CN',
                'value' => '首页',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 2,
                'field' => 'name',
                'locale' => 'ja',
                'value' => 'ホーム',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 2,
                'field' => 'name',
                'locale' => 'ko',
                'value' => '홈',
            ],

            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 3,
                'field' => 'name',
                'locale' => 'en',
                'value' => 'Movies',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 3,
                'field' => 'name',
                'locale' => 'zh-CN',
                'value' => '电影',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 3,
                'field' => 'name',
                'locale' => 'ja',
                'value' => '映画',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 3,
                'field' => 'name',
                'locale' => 'ko',
                'value' => '영화',
            ],

            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 4,
                'field' => 'name',
                'locale' => 'en',
                'value' => 'TV Shows',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 4,
                'field' => 'name',
                'locale' => 'zh-CN',
                'value' => '电视剧',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 4,
                'field' => 'name',
                'locale' => 'ja',
                'value' => 'テレビ番組',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 4,
                'field' => 'name',
                'locale' => 'ko',
                'value' => 'TV 프로그램',
            ],

            // Button labels
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 5,
                'field' => 'label',
                'locale' => 'en',
                'value' => 'Search',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 5,
                'field' => 'label',
                'locale' => 'zh-CN',
                'value' => '搜索',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 5,
                'field' => 'label',
                'locale' => 'ja',
                'value' => '検索',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 5,
                'field' => 'label',
                'locale' => 'ko',
                'value' => '검색',
            ],

            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 6,
                'field' => 'label',
                'locale' => 'en',
                'value' => 'Play',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 6,
                'field' => 'label',
                'locale' => 'zh-CN',
                'value' => '播放',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 6,
                'field' => 'label',
                'locale' => 'ja',
                'value' => '再生',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 6,
                'field' => 'label',
                'locale' => 'ko',
                'value' => '재생',
            ],

            // Error messages
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 7,
                'field' => 'message',
                'locale' => 'en',
                'value' => 'Content not found',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 7,
                'field' => 'message',
                'locale' => 'zh-CN',
                'value' => '未找到内容',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 7,
                'field' => 'message',
                'locale' => 'ja',
                'value' => 'コンテンツが見つかりません',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 7,
                'field' => 'message',
                'locale' => 'ko',
                'value' => '콘텐츠를 찾을 수 없습니다',
            ],

            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 8,
                'field' => 'message',
                'locale' => 'en',
                'value' => 'Network error. Please try again.',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 8,
                'field' => 'message',
                'locale' => 'zh-CN',
                'value' => '网络错误，请重试。',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 8,
                'field' => 'message',
                'locale' => 'ja',
                'value' => 'ネットワークエラーです。もう一度お試しください。',
            ],
            [
                'translatable_type' => 'App\Models\UIElement',
                'translatable_id' => 8,
                'field' => 'message',
                'locale' => 'ko',
                'value' => '네트워크 오류입니다. 다시 시도해주세요.',
            ],
        ];

        foreach ($translations as $translation) {
            Translation::create($translation);
        }
    }
}
