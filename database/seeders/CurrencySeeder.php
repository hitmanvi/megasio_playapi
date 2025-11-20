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
            ['code' => 'USD', 'type' => Currency::TYPE_FIAT, 'symbol' => '$', 'icon' => 'usd.svg', 'sort_id' => 1],
            ['code' => 'EUR', 'type' => Currency::TYPE_FIAT, 'symbol' => '€', 'icon' => 'eur.svg', 'sort_id' => 2],
            ['code' => 'CNY', 'type' => Currency::TYPE_FIAT, 'symbol' => '¥', 'icon' => 'cny.svg', 'sort_id' => 3],
            ['code' => 'JPY', 'type' => Currency::TYPE_FIAT, 'symbol' => '¥', 'icon' => 'jpy.svg', 'sort_id' => 4],
            ['code' => 'KRW', 'type' => Currency::TYPE_FIAT, 'symbol' => '₩', 'icon' => 'krw.svg', 'sort_id' => 5],
            ['code' => 'GBP', 'type' => Currency::TYPE_FIAT, 'symbol' => '£', 'icon' => 'gbp.svg', 'sort_id' => 6],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [
                    'type' => $currency['type'],
                    'symbol' => $currency['symbol'],
                    'icon' => $currency['icon'],
                    'sort_id' => $currency['sort_id'],
                    'enabled' => true,
                ]
            );
        }
    }
}
