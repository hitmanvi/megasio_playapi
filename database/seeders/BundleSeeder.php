<?php

namespace Database\Seeders;

use App\Models\Bundle;
use Illuminate\Database\Seeder;

class BundleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bundles = [
            [
                'name' => 'Starter Pack',
                'description' => 'Perfect for beginners',
                'icon' => null,
                'gold_coin' => 1000,
                'social_coin' => 100,
                'original_price' => 4.99,
                'discount_price' => null,
                'currency' => 'USD',
                'stock' => null, // 无限库存
                'enabled' => true,
                'sort_id' => 1,
            ],
            [
                'name' => 'Popular Pack',
                'description' => 'Most popular choice',
                'icon' => null,
                'gold_coin' => 5500,
                'social_coin' => 550,
                'original_price' => 19.99,
                'discount_price' => 14.99,
                'currency' => 'USD',
                'stock' => null,
                'enabled' => true,
                'sort_id' => 2,
            ],
            [
                'name' => 'Value Pack',
                'description' => 'Great value for money',
                'icon' => null,
                'gold_coin' => 12000,
                'social_coin' => 1200,
                'original_price' => 49.99,
                'discount_price' => 39.99,
                'currency' => 'USD',
                'stock' => null,
                'enabled' => true,
                'sort_id' => 3,
            ],
            [
                'name' => 'Premium Pack',
                'description' => 'For serious players',
                'icon' => null,
                'gold_coin' => 30000,
                'social_coin' => 3000,
                'original_price' => 99.99,
                'discount_price' => 79.99,
                'currency' => 'USD',
                'stock' => null,
                'enabled' => true,
                'sort_id' => 4,
            ],
            [
                'name' => 'Ultimate Pack',
                'description' => 'Maximum value bundle',
                'icon' => null,
                'gold_coin' => 80000,
                'social_coin' => 8000,
                'original_price' => 199.99,
                'discount_price' => 149.99,
                'currency' => 'USD',
                'stock' => null,
                'enabled' => true,
                'sort_id' => 5,
            ],
            [
                'name' => 'Limited Edition',
                'description' => 'Limited stock special offer',
                'icon' => null,
                'gold_coin' => 50000,
                'social_coin' => 10000,
                'original_price' => 99.99,
                'discount_price' => 49.99,
                'currency' => 'USD',
                'stock' => 100, // 限量100个
                'enabled' => true,
                'sort_id' => 6,
            ],
        ];

        foreach ($bundles as $bundleData) {
            Bundle::updateOrCreate(
                ['name' => $bundleData['name'], 'currency' => $bundleData['currency']],
                $bundleData
            );
        }
    }
}
