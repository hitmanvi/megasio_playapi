<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Brand;
use App\Models\Tag;
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

        foreach ($games as $gameData) {
            Game::create($gameData);
        }
    }
}