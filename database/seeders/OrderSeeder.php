<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Game;
use App\Models\Brand;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 假设使用user_id=1
        $userId = 1;

        // 获取品牌和游戏
        $brands = Brand::all()->keyBy('provider');
        $games = Game::all();

        if ($games->isEmpty()) {
            $this->command->warn('No games found. Please run GameSeeder first.');
            return;
        }

        // 生成订单数据
        $orders = [
            // 已完成订单
            [
                'user_id' => $userId,
                'game_id' => $games->first()->id,
                'brand_id' => $brands['netflix']->id ?? $games->first()->brand_id,
                'amount' => 100.00000000,
                'payout' => 150.00000000,
                'status' => Order::STATUS_COMPLETED,
                'currency' => 'USD',
                'payment_currency' => 'USD',
                'payment_amount' => 100.00000000,
                'payment_payout' => 150.00000000,
                'notes' => 'Order completed successfully',
                'finished_at' => now()->subDays(5),
                'order_id' => 'ORD' . strtoupper(uniqid()),
                'out_id' => 'EXT_001',
            ],
            [
                'user_id' => $userId,
                'game_id' => $games->skip(1)->first()->id ?? $games->first()->id,
                'brand_id' => $brands['disney']->id ?? $games->first()->brand_id,
                'amount' => 50.00000000,
                'payout' => 75.00000000,
                'status' => Order::STATUS_COMPLETED,
                'currency' => 'USD',
                'payment_currency' => 'CNY',
                'payment_amount' => 340.00000000, // 假设1:6.8汇率
                'payment_payout' => 510.00000000,
                'notes' => 'Order completed with CNY payment',
                'finished_at' => now()->subDays(3),
                'order_id' => 'ORD' . strtoupper(uniqid()),
                'out_id' => 'EXT_002',
            ],
            // 待处理订单
            [
                'user_id' => $userId,
                'game_id' => $games->random()->id,
                'brand_id' => $brands['netflix']->id ?? $games->first()->brand_id,
                'amount' => 75.00000000,
                'payout' => null,
                'status' => Order::STATUS_PENDING,
                'currency' => 'USD',
                'payment_currency' => 'USD',
                'payment_amount' => 75.00000000,
                'payment_payout' => null,
                'notes' => 'Order pending',
                'finished_at' => null,
                'order_id' => 'ORD' . strtoupper(uniqid()),
                'out_id' => null,
            ],
            [
                'user_id' => $userId,
                'game_id' => $games->random()->id,
                'brand_id' => $brands['amazon']->id ?? $games->first()->brand_id,
                'amount' => 200.00000000,
                'payout' => null,
                'status' => Order::STATUS_PENDING,
                'currency' => 'EUR',
                'payment_currency' => 'EUR',
                'payment_amount' => 200.00000000,
                'payment_payout' => null,
                'notes' => 'Order pending',
                'finished_at' => null,
                'order_id' => 'ORD' . strtoupper(uniqid()),
                'out_id' => null,
            ],
            // 失败的订单
            [
                'user_id' => $userId,
                'game_id' => $games->random()->id,
                'brand_id' => $brands['hbo']->id ?? $games->first()->brand_id,
                'amount' => 30.00000000,
                'payout' => null,
                'status' => Order::STATUS_FAILED,
                'currency' => 'USD',
                'payment_currency' => 'USD',
                'payment_amount' => 30.00000000,
                'payment_payout' => null,
                'notes' => 'Payment failed',
                'finished_at' => now()->subDays(1),
                'order_id' => 'ORD' . strtoupper(uniqid()),
                'out_id' => null,
            ],
            // 已取消的订单
            [
                'user_id' => $userId,
                'game_id' => $games->random()->id,
                'brand_id' => $brands['disney']->id ?? $games->first()->brand_id,
                'amount' => 25.00000000,
                'payout' => null,
                'status' => Order::STATUS_CANCELLED,
                'currency' => 'CNY',
                'payment_currency' => 'CNY',
                'payment_amount' => 25.00000000,
                'payment_payout' => null,
                'notes' => 'Order cancelled by user',
                'finished_at' => now()->subHours(2),
                'order_id' => 'ORD' . strtoupper(uniqid()),
                'out_id' => null,
            ],
        ];

        foreach ($orders as $orderData) {
            Order::create($orderData);
        }

        $this->command->info('OrderSeeder completed: ' . count($orders) . ' orders created.');
    }
}
