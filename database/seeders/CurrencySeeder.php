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
            ['code' => 'USD', 'name' => 'US Dollar', 'type' => Currency::TYPE_FIAT, 'symbol' => '$', 'icon' => 'usd.svg', 'sort_id' => 1],
            ['code' => 'EUR', 'name' => 'Euro', 'type' => Currency::TYPE_FIAT, 'symbol' => '€', 'icon' => 'eur.svg', 'sort_id' => 2],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'type' => Currency::TYPE_FIAT, 'symbol' => '¥', 'icon' => 'cny.svg', 'sort_id' => 3],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'type' => Currency::TYPE_FIAT, 'symbol' => '¥', 'icon' => 'jpy.svg', 'sort_id' => 4],
            ['code' => 'KRW', 'name' => 'South Korean Won', 'type' => Currency::TYPE_FIAT, 'symbol' => '₩', 'icon' => 'krw.svg', 'sort_id' => 5],
            ['code' => 'GBP', 'name' => 'British Pound', 'type' => Currency::TYPE_FIAT, 'symbol' => '£', 'icon' => 'gbp.svg', 'sort_id' => 6],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
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
