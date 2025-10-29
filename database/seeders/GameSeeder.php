<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Brand;
use App\Models\GameCategory;
use App\Models\Theme;
use App\Models\Translation;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing brands, categories, and themes
        $brands = Brand::all()->keyBy('provider');
        $categories = GameCategory::all();
        
        // Map category names to IDs (using English names)
        $categoryMap = [];
        foreach ($categories as $category) {
            $name = strtolower(str_replace([' ', '-'], ['', '_'], $category->getName('en') ?? ''));
            $categoryMap[$name] = $category->id;
        }
        
        $themes = Theme::all()->keyBy('name');

        $games = [
            [
                'brand_id' => $brands['netflix']->id,
                'category_id' => $categoryMap['movie'] ?? null,
                'out_id' => 'netflix_001',
                'name' => 'The Matrix',
                'thumbnail' => 'https://example.com/thumbnails/matrix.jpg',
                'sort_id' => 1,
                'enabled' => true,
                'memo' => 'Classic sci-fi action movie',
                'languages' => ['en', 'zh-CN', 'ja', 'ko'],
            ],
            [
                'brand_id' => $brands['netflix']->id,
                'category_id' => $categoryMap['movie'] ?? null,
                'out_id' => 'netflix_002',
                'name' => 'The Hangover',
                'thumbnail' => 'https://example.com/thumbnails/hangover.jpg',
                'sort_id' => 2,
                'enabled' => true,
                'memo' => 'Hilarious comedy about a bachelor party gone wrong',
                'languages' => ['en', 'zh-CN', 'ko'],
            ],
            [
                'brand_id' => $brands['disney']->id,
                'category_id' => $categoryMap['movie'] ?? null,
                'out_id' => 'disney_001',
                'name' => 'Pirates of the Caribbean',
                'thumbnail' => 'https://example.com/thumbnails/pirates.jpg',
                'sort_id' => 3,
                'enabled' => true,
                'memo' => 'Epic adventure on the high seas',
                'languages' => ['en', 'zh-CN', 'ja', 'ko', 'fr'],
            ],
            [
                'brand_id' => $brands['disney']->id,
                'category_id' => $categoryMap['anime'] ?? null,
                'out_id' => 'disney_002',
                'name' => 'Your Name',
                'thumbnail' => 'https://example.com/thumbnails/yourname.jpg',
                'sort_id' => 4,
                'enabled' => true,
                'memo' => 'Beautiful anime romance story',
                'languages' => ['en', 'zh-CN', 'ja'],
            ],
            [
                'brand_id' => $brands['amazon']->id,
                'category_id' => $categoryMap['tvseries'] ?? null,
                'out_id' => 'amazon_001',
                'name' => 'The Boys',
                'thumbnail' => 'https://example.com/thumbnails/theboys.jpg',
                'sort_id' => 5,
                'enabled' => true,
                'memo' => 'Dark superhero drama series',
                'languages' => ['en', 'zh-CN', 'ja', 'ko'],
            ],
            [
                'brand_id' => $brands['hbo']->id,
                'category_id' => $categoryMap['tvseries'] ?? null,
                'out_id' => 'hbo_001',
                'name' => 'Game of Thrones',
                'thumbnail' => 'https://example.com/thumbnails/got.jpg',
                'sort_id' => 6,
                'enabled' => true,
                'memo' => 'Epic fantasy thriller series',
                'languages' => ['en', 'zh-CN', 'ja', 'ko', 'es'],
            ],
            [
                'brand_id' => $brands['apple']->id,
                'category_id' => $categoryMap['movie'] ?? null,
                'out_id' => 'apple_001',
                'name' => 'Foundation',
                'thumbnail' => 'https://example.com/thumbnails/foundation.jpg',
                'sort_id' => 7,
                'enabled' => true,
                'memo' => 'Epic sci-fi series based on Asimov novels',
                'languages' => ['en', 'zh-CN', 'ja'],
            ],
            [
                'brand_id' => $brands['youtube']->id,
                'category_id' => $categoryMap['documentary'] ?? null,
                'out_id' => 'youtube_001',
                'name' => 'Free Solo',
                'thumbnail' => 'https://example.com/thumbnails/freesolo.jpg',
                'sort_id' => 8,
                'enabled' => true,
                'memo' => 'Incredible documentary about free solo climbing',
                'languages' => ['en', 'zh-CN'],
            ],
            [
                'brand_id' => $brands['netflix']->id,
                'category_id' => $categoryMap['webseries'] ?? null,
                'out_id' => 'netflix_003',
                'name' => 'Stranger Things',
                'thumbnail' => 'https://example.com/thumbnails/strangerthings.jpg',
                'sort_id' => 9,
                'enabled' => true,
                'memo' => 'Supernatural horror series',
                'languages' => ['en', 'zh-CN', 'ja', 'ko', 'es', 'fr'],
            ],
            [
                'brand_id' => $brands['disney']->id,
                'category_id' => $categoryMap['shortfilm'] ?? null,
                'out_id' => 'disney_003',
                'name' => 'Lava',
                'thumbnail' => 'https://example.com/thumbnails/lava.jpg',
                'sort_id' => 10,
                'enabled' => true,
                'memo' => 'Charming animated short film',
                'languages' => ['en'],
            ],
            [
                'brand_id' => $brands['amazon']->id,
                'category_id' => $categoryMap['movie'] ?? null,
                'out_id' => 'amazon_002',
                'name' => 'The Big Sick',
                'thumbnail' => 'https://example.com/thumbnails/bigsick.jpg',
                'sort_id' => 11,
                'enabled' => false,
                'memo' => 'Romantic comedy temporarily disabled',
                'languages' => ['en', 'zh-CN'],
            ],
            [
                'brand_id' => $brands['hbo']->id,
                'category_id' => $categoryMap['tvseries'] ?? null,
                'out_id' => 'hbo_002',
                'name' => 'Succession',
                'thumbnail' => 'https://example.com/thumbnails/succession.jpg',
                'sort_id' => 12,
                'enabled' => true,
                'memo' => 'Corporate drama series',
                'languages' => ['en', 'zh-CN', 'ja', 'ko'],
            ],
        ];

        // Map old theme tag names to new theme names
        $themeMap = [
            'action' => 'Action',
            'adventure' => 'Adventure',
            'comedy' => 'Comedy',
            'drama' => 'Drama',
            'horror' => 'Horror',
            'romance' => 'Romance',
            'sci-fi' => 'Science Fiction',
            'thriller' => 'Thriller',
        ];

        // Map game names to theme names
        $gameThemesMap = [
            'The Matrix' => ['Action', 'Science Fiction'],
            'The Hangover' => ['Comedy'],
            'Pirates of the Caribbean' => ['Adventure'],
            'Your Name' => ['Romance'],
            'The Boys' => ['Drama'],
            'Game of Thrones' => ['Thriller', 'Drama'],
            'Foundation' => ['Science Fiction'],
            'Free Solo' => ['Adventure'],
            'Stranger Things' => ['Horror', 'Thriller'],
            'Lava' => ['Comedy', 'Romance'],
            'The Big Sick' => ['Romance', 'Comedy'],
            'Succession' => ['Drama'],
        ];

        foreach ($games as $index => $gameData) {
            // Get themes for this game
            $themeNames = $gameThemesMap[$gameData['name']] ?? [];

            // Create the game
            $game = Game::create($gameData);

            // Attach themes
            foreach ($themeNames as $themeName) {
                if (isset($themes[$themeName])) {
                    $game->themes()->attach($themes[$themeName]->id);
                }
            }

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