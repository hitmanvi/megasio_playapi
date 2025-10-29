<?php

namespace Database\Seeders;

use App\Models\GameCategory;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing category tags from tags table
        $categoryTags = Tag::all()->keyBy('name');
        
        // Map tag names to category data (based on TagSeeder)
        $categoryData = [
            'movie' => [
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
            'tv-series' => [
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
            'anime' => [
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
            'documentary' => [
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
            'short-film' => [
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
            'web-series' => [
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

        // Create game categories and migrate data from tags
        $tagToCategoryMap = [];
        
        foreach ($categoryData as $tagName => $data) {
            $translations = $data['translations'];
            unset($data['translations']);
            
            // Create the game category
            $category = GameCategory::create($data);
            
            // Set translations
            $category->setNames($translations);
            
            // Store mapping from old tag ID to new category ID
            if (isset($categoryTags[$tagName])) {
                $tagToCategoryMap[$categoryTags[$tagName]->id] = $category->id;
            }
        }
        
        // If there are existing games, update their category_id
        if (!empty($tagToCategoryMap)) {
            foreach ($tagToCategoryMap as $tagId => $categoryId) {
                DB::table('games')
                    ->where('category_id', $tagId)
                    ->update(['category_id' => $categoryId]);
            }
        }
        
        // Add foreign key constraint after data migration
        // Note: This should be done carefully to avoid duplicate constraint errors
        try {
            DB::statement('ALTER TABLE games ADD CONSTRAINT games_category_id_foreign FOREIGN KEY (category_id) REFERENCES game_categories(id)');
        } catch (\Exception $e) {
            // Constraint might already exist, ignore
        }
    }
}
