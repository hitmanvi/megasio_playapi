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
                'name' => 'Action',
                'icon' => 'fas fa-fist-raised',
                'enabled' => true,
                'sort_id' => 1,
            ],
            [
                'name' => 'Adventure',
                'icon' => 'fas fa-map-marked-alt',
                'enabled' => true,
                'sort_id' => 2,
            ],
            [
                'name' => 'Comedy',
                'icon' => 'fas fa-laugh',
                'enabled' => true,
                'sort_id' => 3,
            ],
            [
                'name' => 'Drama',
                'icon' => 'fas fa-theater-masks',
                'enabled' => true,
                'sort_id' => 4,
            ],
            [
                'name' => 'Horror',
                'icon' => 'fas fa-ghost',
                'enabled' => true,
                'sort_id' => 5,
            ],
            [
                'name' => 'Romance',
                'icon' => 'fas fa-heart',
                'enabled' => true,
                'sort_id' => 6,
            ],
            [
                'name' => 'Science Fiction',
                'icon' => 'fas fa-rocket',
                'enabled' => true,
                'sort_id' => 7,
            ],
            [
                'name' => 'Thriller',
                'icon' => 'fas fa-exclamation-triangle',
                'enabled' => true,
                'sort_id' => 8,
            ],
        ];

        foreach ($themes as $theme) {
            Theme::create($theme);
        }
    }
}
