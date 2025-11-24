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
            // 法币
            ['code' => 'USD', 'name' => 'US Dollar', 'type' => Currency::TYPE_FIAT, 'symbol' => '$', 'icon' => 'https://s2.coinmarketcap.com/static/cloud/img/fiat-flags/USD.svg', 'sort_id' => 1],
            ['code' => 'EUR', 'name' => 'Euro', 'type' => Currency::TYPE_FIAT, 'symbol' => '€', 'icon' => 'https://s2.coinmarketcap.com/static/cloud/img/fiat-flags/EUR.svg', 'sort_id' => 2],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'type' => Currency::TYPE_FIAT, 'symbol' => '¥', 'icon' => 'https://s2.coinmarketcap.com/static/cloud/img/fiat-flags/CNY.svg', 'sort_id' => 3],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'type' => Currency::TYPE_FIAT, 'symbol' => '¥', 'icon' => 'https://s2.coinmarketcap.com/static/cloud/img/fiat-flags/JPY.svg', 'sort_id' => 4],
            ['code' => 'KRW', 'name' => 'South Korean Won', 'type' => Currency::TYPE_FIAT, 'symbol' => '₩', 'icon' => 'https://s2.coinmarketcap.com/static/cloud/img/fiat-flags/KRW.svg', 'sort_id' => 5],
            ['code' => 'GBP', 'name' => 'British Pound', 'type' => Currency::TYPE_FIAT, 'symbol' => '£', 'icon' => 'https://s2.coinmarketcap.com/static/cloud/img/fiat-flags/GBP.svg', 'sort_id' => 6],
            // 加密货币
            ['code' => 'BTC', 'name' => 'Bitcoin', 'type' => Currency::TYPE_CRYPTO, 'symbol' => '₿', 'icon' => 'https://s2.coinmarketcap.com/static/img/coins/64x64/1.png', 'sort_id' => 10],
            ['code' => 'ETH', 'name' => 'Ethereum', 'type' => Currency::TYPE_CRYPTO, 'symbol' => 'Ξ', 'icon' => 'https://s2.coinmarketcap.com/static/img/coins/64x64/1027.png', 'sort_id' => 11],
            ['code' => 'USDT', 'name' => 'Tether', 'type' => Currency::TYPE_CRYPTO, 'symbol' => '₮', 'icon' => 'usdt.svg', 'sort_id' => 12],
            ['code' => 'BNB', 'name' => 'BNB', 'type' => Currency::TYPE_CRYPTO, 'symbol' => 'BNB', 'icon' => 'https://s2.coinmarketcap.com/static/img/coins/64x64/1839.png', 'sort_id' => 13],
            ['code' => 'SOL', 'name' => 'Solana', 'type' => Currency::TYPE_CRYPTO, 'symbol' => '◎', 'icon' => 'sol.svg', 'sort_id' => 14],
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
