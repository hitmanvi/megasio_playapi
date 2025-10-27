<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Brand;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing brands and tags
        $brands = Brand::all()->keyBy('provider');
        $tags = Tag::all()->keyBy('name');

        $games = [
            [
                'brand_id' => $brands['netflix']->id,
                'category_id' => $tags['movie']->id,
                'theme_id' => $tags['action']->id,
                'out_id' => 'netflix_001',
                'name' => 'The Matrix',
                'thumbnail' => 'https://example.com/thumbnails/matrix.jpg',
                'sort_id' => 1,
                'enabled' => true,
                'memo' => 'Classic sci-fi action movie',
            ],
            [
                'brand_id' => $brands['netflix']->id,
                'category_id' => $tags['movie']->id,
                'theme_id' => $tags['comedy']->id,
                'out_id' => 'netflix_002',
                'name' => 'The Hangover',
                'thumbnail' => 'https://example.com/thumbnails/hangover.jpg',
                'sort_id' => 2,
                'enabled' => true,
                'memo' => 'Hilarious comedy about a bachelor party gone wrong',
            ],
            [
                'brand_id' => $brands['disney']->id,
                'category_id' => $tags['movie']->id,
                'theme_id' => $tags['adventure']->id,
                'out_id' => 'disney_001',
                'name' => 'Pirates of the Caribbean',
                'thumbnail' => 'https://example.com/thumbnails/pirates.jpg',
                'sort_id' => 3,
                'enabled' => true,
                'memo' => 'Epic adventure on the high seas',
            ],
            [
                'brand_id' => $brands['disney']->id,
                'category_id' => $tags['anime']->id,
                'theme_id' => $tags['romance']->id,
                'out_id' => 'disney_002',
                'name' => 'Your Name',
                'thumbnail' => 'https://example.com/thumbnails/yourname.jpg',
                'sort_id' => 4,
                'enabled' => true,
                'memo' => 'Beautiful anime romance story',
            ],
            [
                'brand_id' => $brands['amazon']->id,
                'category_id' => $tags['tv-series']->id,
                'theme_id' => $tags['drama']->id,
                'out_id' => 'amazon_001',
                'name' => 'The Boys',
                'thumbnail' => 'https://example.com/thumbnails/theboys.jpg',
                'sort_id' => 5,
                'enabled' => true,
                'memo' => 'Dark superhero drama series',
            ],
            [
                'brand_id' => $brands['hbo']->id,
                'category_id' => $tags['tv-series']->id,
                'theme_id' => $tags['thriller']->id,
                'out_id' => 'hbo_001',
                'name' => 'Game of Thrones',
                'thumbnail' => 'https://example.com/thumbnails/got.jpg',
                'sort_id' => 6,
                'enabled' => true,
                'memo' => 'Epic fantasy thriller series',
            ],
            [
                'brand_id' => $brands['apple']->id,
                'category_id' => $tags['movie']->id,
                'theme_id' => $tags['sci-fi']->id,
                'out_id' => 'apple_001',
                'name' => 'Foundation',
                'thumbnail' => 'https://example.com/thumbnails/foundation.jpg',
                'sort_id' => 7,
                'enabled' => true,
                'memo' => 'Epic sci-fi series based on Asimov novels',
            ],
            [
                'brand_id' => $brands['youtube']->id,
                'category_id' => $tags['documentary']->id,
                'theme_id' => $tags['adventure']->id,
                'out_id' => 'youtube_001',
                'name' => 'Free Solo',
                'thumbnail' => 'https://example.com/thumbnails/freesolo.jpg',
                'sort_id' => 8,
                'enabled' => true,
                'memo' => 'Incredible documentary about free solo climbing',
            ],
            [
                'brand_id' => $brands['netflix']->id,
                'category_id' => $tags['web-series']->id,
                'theme_id' => $tags['horror']->id,
                'out_id' => 'netflix_003',
                'name' => 'Stranger Things',
                'thumbnail' => 'https://example.com/thumbnails/strangerthings.jpg',
                'sort_id' => 9,
                'enabled' => true,
                'memo' => 'Supernatural horror series',
            ],
            [
                'brand_id' => $brands['disney']->id,
                'category_id' => $tags['short-film']->id,
                'theme_id' => $tags['comedy']->id,
                'out_id' => 'disney_003',
                'name' => 'Lava',
                'thumbnail' => 'https://example.com/thumbnails/lava.jpg',
                'sort_id' => 10,
                'enabled' => true,
                'memo' => 'Charming animated short film',
            ],
            [
                'brand_id' => $brands['amazon']->id,
                'category_id' => $tags['movie']->id,
                'theme_id' => $tags['romance']->id,
                'out_id' => 'amazon_002',
                'name' => 'The Big Sick',
                'thumbnail' => 'https://example.com/thumbnails/bigsick.jpg',
                'sort_id' => 11,
                'enabled' => false,
                'memo' => 'Romantic comedy temporarily disabled',
            ],
            [
                'brand_id' => $brands['hbo']->id,
                'category_id' => $tags['tv-series']->id,
                'theme_id' => $tags['drama']->id,
                'out_id' => 'hbo_002',
                'name' => 'Succession',
                'thumbnail' => 'https://example.com/thumbnails/succession.jpg',
                'sort_id' => 12,
                'enabled' => true,
                'memo' => 'Corporate drama series',
            ],
        ];

        foreach ($games as $index => $gameData) {
            $game = Game::create($gameData);

            // 创建游戏的翻译数据
            $translations = [
                ['locale' => 'en', 'value' => $gameData['name']],
                ['locale' => 'zh-CN', 'value' => $this->getChineseName($gameData['name'])],
                ['locale' => 'ja', 'value' => $this->getJapaneseName($gameData['name'])],
                ['locale' => 'ko', 'value' => $this->getKoreanName($gameData['name'])],
            ];

            foreach ($translations as $translation) {
                Translation::create([
                    'translatable_type' => Game::class,
                    'translatable_id' => $game->id,
                    'field' => 'name',
                    'locale' => $translation['locale'],
                    'value' => $translation['value'],
                ]);
            }
        }
    }

    /**
     * 获取中文名称
     */
    private function getChineseName(string $name): string
    {
        $names = [
            'The Matrix' => '黑客帝国',
            'The Hangover' => '宿醉',
            'Pirates of the Caribbean' => '加勒比海盗',
            'Your Name' => '你的名字',
            'The Boys' => '黑袍纠察队',
            'Game of Thrones' => '权力的游戏',
            'Foundation' => '基地',
            'Free Solo' => '徒手攀岩',
            'Stranger Things' => '怪奇物语',
            'Lava' => '熔岩',
            'The Big Sick' => '大病',
            'Succession' => '继承之战',
        ];

        return $names[$name] ?? $name;
    }

    /**
     * 获取日文名称
     */
    private function getJapaneseName(string $name): string
    {
        $names = [
            'The Matrix' => 'マトリックス',
            'The Hangover' => 'ハングオーバー',
            'Pirates of the Caribbean' => 'パイレーツ・オブ・カリビアン',
            'Your Name' => '君の名は。',
            'The Boys' => 'ザ・ボーイズ',
            'Game of Thrones' => 'ゲーム・オブ・スローンズ',
            'Foundation' => 'ファウンデーション',
            'Free Solo' => 'フリーソロ',
            'Stranger Things' => 'ストレンジャー・シングス',
            'Lava' => 'ラバ',
            'The Big Sick' => 'ビッグ・シック',
            'Succession' => 'サクセッション',
        ];

        return $names[$name] ?? $name;
    }

    /**
     * 获取韩文名称
     */
    private function getKoreanName(string $name): string
    {
        $names = [
            'The Matrix' => '매트릭스',
            'The Hangover' => '행오버',
            'Pirates of the Caribbean' => '캐리비안의 해적',
            'Your Name' => '너의 이름은',
            'The Boys' => '보이즈',
            'Game of Thrones' => '왕좌의 게임',
            'Foundation' => '파운데이션',
            'Free Solo' => '프리 솔로',
            'Stranger Things' => '이상한 물건',
            'Lava' => '용암',
            'The Big Sick' => '더 빅 식',
            'Succession' => '승계',
        ];

        return $names[$name] ?? $name;
    }
}