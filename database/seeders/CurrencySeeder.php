<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'symbol' => '$', 'icon' => 'usd.svg', 'sort_order' => 1],
            ['code' => 'EUR', 'symbol' => '€', 'icon' => 'eur.svg', 'sort_order' => 2],
            ['code' => 'CNY', 'symbol' => '¥', 'icon' => 'cny.svg', 'sort_order' => 3],
            ['code' => 'JPY', 'symbol' => '¥', 'icon' => 'jpy.svg', 'sort_order' => 4],
            ['code' => 'KRW', 'symbol' => '₩', 'icon' => 'krw.svg', 'sort_order' => 5],
            ['code' => 'GBP', 'symbol' => '£', 'icon' => 'gbp.svg', 'sort_order' => 6],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [
                    'symbol' => $currency['symbol'],
                    'icon' => $currency['icon'],
                    'sort_order' => $currency['sort_order'],
                    'enabled' => true,
                ]
            );
        }
    }
}
