<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Netflix',
                'provider' => 'netflix',
                'restricted_region' => ['CN', 'RU'],
                'sort_id' => 1,
                'enabled' => true,
                'maintain_start' => null,
                'maintain_end' => null,
                'maintain_auto' => false,
                'translations' => [
                    'en' => 'Netflix',
                    'zh-CN' => '网飞',
                    'ja' => 'ネットフリックス',
                    'ko' => '넷플릭스',
                ]
            ],
            [
                'name' => 'Disney+',
                'provider' => 'disney',
                'restricted_region' => ['CN'],
                'sort_id' => 2,
                'enabled' => true,
                'maintain_start' => null,
                'maintain_end' => null,
                'maintain_auto' => false,
                'translations' => [
                    'en' => 'Disney+',
                    'zh-CN' => '迪士尼+',
                    'ja' => 'ディズニー+',
                    'ko' => '디즈니+',
                ]
            ],
            [
                'name' => 'Amazon Prime',
                'provider' => 'amazon',
                'restricted_region' => [],
                'sort_id' => 3,
                'enabled' => true,
                'maintain_start' => null,
                'maintain_end' => null,
                'maintain_auto' => false,
                'translations' => [
                    'en' => 'Amazon Prime Video',
                    'zh-CN' => '亚马逊Prime视频',
                    'ja' => 'アマゾンプライムビデオ',
                    'ko' => '아마존 프라임 비디오',
                ]
            ],
            [
                'name' => 'HBO Max',
                'provider' => 'hbo',
                'restricted_region' => ['CN', 'RU'],
                'sort_id' => 4,
                'enabled' => true,
                'maintain_start' => null,
                'maintain_end' => null,
                'maintain_auto' => false,
                'translations' => [
                    'en' => 'HBO Max',
                    'zh-CN' => 'HBO Max',
                    'ja' => 'HBO Max',
                    'ko' => 'HBO Max',
                ]
            ],
            [
                'name' => 'Apple TV+',
                'provider' => 'apple',
                'restricted_region' => [],
                'sort_id' => 5,
                'enabled' => true,
                'maintain_start' => null,
                'maintain_end' => null,
                'maintain_auto' => false,
                'translations' => [
                    'en' => 'Apple TV+',
                    'zh-CN' => '苹果TV+',
                    'ja' => 'Apple TV+',
                    'ko' => 'Apple TV+',
                ]
            ],
            [
                'name' => 'YouTube Premium',
                'provider' => 'youtube',
                'restricted_region' => [],
                'sort_id' => 6,
                'enabled' => true,
                'maintain_start' => null,
                'maintain_end' => null,
                'maintain_auto' => false,
                'translations' => [
                    'en' => 'YouTube Premium',
                    'zh-CN' => 'YouTube Premium',
                    'ja' => 'YouTube Premium',
                    'ko' => 'YouTube Premium',
                ]
            ],
        ];

        foreach ($brands as $brandData) {
            $translations = $brandData['translations'];
            unset($brandData['translations']);

            // Create the brand
            $brand = Brand::create($brandData);

            // Set translations using the Translatable trait
            $brand->setNames($translations);
        }
    }
}
