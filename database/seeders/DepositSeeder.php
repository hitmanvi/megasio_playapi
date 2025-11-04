<?php

namespace Database\Seeders;

use App\Models\Deposit;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DepositSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 获取第一个用户，如果没有则创建
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        // 获取存款类型的支付方式
        $depositPaymentMethods = PaymentMethod::where('type', PaymentMethod::TYPE_DEPOSIT)
            ->where('enabled', true)
            ->get();

        if ($depositPaymentMethods->isEmpty()) {
            $this->command->warn('No deposit payment methods found. Please run PaymentMethodSeeder first.');
            return;
        }

        $deposits = [
            // 待处理订单
            [
                'amount' => 100.00,
                'currency' => 'USD',
                'status' => Deposit::STATUS_PENDING,
                'pay_status' => Deposit::PAY_STATUS_PENDING,
                'days_ago' => 0,
                'payment_method_key' => 'credit_card_usd',
                'expired_at_offset' => 30, // 30分钟后过期
            ],
            [
                'amount' => 500.00,
                'currency' => 'USD',
                'status' => Deposit::STATUS_PENDING,
                'pay_status' => Deposit::PAY_STATUS_PENDING,
                'days_ago' => 1,
                'payment_method_key' => 'paypal_usd',
                'expired_at_offset' => 30,
            ],
            // 处理中订单
            [
                'amount' => 200.00,
                'currency' => 'USD',
                'status' => Deposit::STATUS_PROCESSING,
                'pay_status' => Deposit::PAY_STATUS_PENDING,
                'days_ago' => 2,
                'payment_method_key' => 'debit_card_usd',
                'expired_at_offset' => 30,
                'out_trade_no' => 'SOPAY' . strtoupper(Str::random(10)),
            ],
            // 已完成订单
            [
                'amount' => 1000.00,
                'currency' => 'USD',
                'status' => Deposit::STATUS_COMPLETED,
                'pay_status' => Deposit::PAY_STATUS_PAID,
                'days_ago' => 5,
                'payment_method_key' => 'credit_card_usd',
                'actual_amount' => 1000.00,
                'pay_fee' => 0.00,
                'out_trade_no' => 'SOPAY' . strtoupper(Str::random(10)),
                'finished_at_offset' => 5,
            ],
            [
                'amount' => 750.00,
                'currency' => 'USD',
                'status' => Deposit::STATUS_COMPLETED,
                'pay_status' => Deposit::PAY_STATUS_PAID,
                'days_ago' => 7,
                'payment_method_key' => 'bank_transfer_usd_deposit',
                'actual_amount' => 745.00,
                'pay_fee' => 5.00,
                'out_trade_no' => 'SOPAY' . strtoupper(Str::random(10)),
                'finished_at_offset' => 7,
            ],
            // 失败订单
            [
                'amount' => 300.00,
                'currency' => 'USD',
                'status' => Deposit::STATUS_FAILED,
                'pay_status' => Deposit::PAY_STATUS_FAILED,
                'days_ago' => 3,
                'payment_method_key' => 'paypal_usd',
                'out_trade_no' => 'SOPAY' . strtoupper(Str::random(10)),
                'finished_at_offset' => 3,
            ],
            // 已取消订单
            [
                'amount' => 150.00,
                'currency' => 'USD',
                'status' => Deposit::STATUS_CANCELLED,
                'pay_status' => Deposit::PAY_STATUS_CANCELLED,
                'days_ago' => 4,
                'payment_method_key' => 'ach_usd_deposit',
                'finished_at_offset' => 4,
            ],
            // 已过期订单
            [
                'amount' => 50.00,
                'currency' => 'USD',
                'status' => Deposit::STATUS_EXPIRED,
                'pay_status' => Deposit::PAY_STATUS_PENDING,
                'days_ago' => 10,
                'payment_method_key' => 'debit_card_usd',
                'expired_at_offset' => -14400, // 10天前过期（负数表示已经过期）
            ],
        ];

        foreach ($deposits as $depositData) {
            // 查找支付方式
            $paymentMethod = PaymentMethod::where('key', $depositData['payment_method_key'])->first();
            
            if (!$paymentMethod) {
                // 如果找不到指定的支付方式，使用第一个可用的支付方式
                $paymentMethod = $depositPaymentMethods->first();
            }

            // 生成订单号
            $orderNo = 'DEP' . strtoupper(Str::ulid()->toString());

            // 计算时间
            $createdAt = now()->subDays($depositData['days_ago']);
            $expiredAt = isset($depositData['expired_at_offset']) 
                ? $createdAt->copy()->addMinutes($depositData['expired_at_offset'])
                : $createdAt->copy()->addMinutes(30);
            
            $finishedAt = isset($depositData['finished_at_offset'])
                ? now()->subDays($depositData['finished_at_offset'])
                : null;

            Deposit::updateOrCreate(
                ['order_no' => $orderNo],
                [
                    'user_id' => $user->id,
                    'out_trade_no' => $depositData['out_trade_no'] ?? null,
                    'currency' => $depositData['currency'],
                    'amount' => $depositData['amount'],
                    'actual_amount' => $depositData['actual_amount'] ?? null,
                    'payment_method_id' => $paymentMethod->id,
                    'deposit_info' => isset($depositData['deposit_info']) ? $depositData['deposit_info'] : null,
                    'extra_info' => isset($depositData['extra_info']) ? $depositData['extra_info'] : null,
                    'status' => $depositData['status'],
                    'pay_status' => $depositData['pay_status'],
                    'pay_fee' => $depositData['pay_fee'] ?? null,
                    'user_ip' => '192.168.1.' . rand(1, 255),
                    'expired_at' => $expiredAt,
                    'finished_at' => $finishedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }

        $this->command->info('DepositSeeder completed successfully.');
    }
}
