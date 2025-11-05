<?php

namespace Database\Seeders;

use App\Models\Withdraw;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WithdrawSeeder extends Seeder
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

        // 获取提款类型的支付方式
        $withdrawPaymentMethods = PaymentMethod::where('type', PaymentMethod::TYPE_WITHDRAW)
            ->where('enabled', true)
            ->get();

        if ($withdrawPaymentMethods->isEmpty()) {
            $this->command->warn('No withdraw payment methods found. Please run PaymentMethodSeeder first.');
            return;
        }

        $withdraws = [
            // 待处理订单
            [
                'amount' => 100.00,
                'currency' => 'USD',
                'status' => Withdraw::STATUS_PENDING,
                'pay_status' => Withdraw::PAY_STATUS_PENDING,
                'approved' => false,
                'days_ago' => 0,
                'payment_method_key' => 'ach_usd_withdraw',
            ],
            [
                'amount' => 500.00,
                'currency' => 'USD',
                'status' => Withdraw::STATUS_PENDING,
                'pay_status' => Withdraw::PAY_STATUS_PENDING,
                'approved' => false,
                'days_ago' => 1,
                'payment_method_key' => 'bank_transfer_usd_withdraw',
            ],
            // 处理中订单
            [
                'amount' => 200.00,
                'currency' => 'USD',
                'status' => Withdraw::STATUS_PROCESSING,
                'pay_status' => Withdraw::PAY_STATUS_PENDING,
                'approved' => true,
                'days_ago' => 2,
                'payment_method_key' => 'ach_usd_withdraw',
                'out_trade_no' => 'SOPAY' . strtoupper(Str::random(10)),
            ],
            // 已完成订单
            [
                'amount' => 1000.00,
                'currency' => 'USD',
                'status' => Withdraw::STATUS_COMPLETED,
                'pay_status' => Withdraw::PAY_STATUS_PAID,
                'approved' => true,
                'days_ago' => 5,
                'payment_method_key' => 'bank_transfer_usd_withdraw',
                'actual_amount' => 1000.00,
                'fee' => 0.00,
                'out_trade_no' => 'SOPAY' . strtoupper(Str::random(10)),
                'completed_at_offset' => 5,
            ],
            [
                'amount' => 750.00,
                'currency' => 'USD',
                'status' => Withdraw::STATUS_COMPLETED,
                'pay_status' => Withdraw::PAY_STATUS_PAID,
                'approved' => true,
                'days_ago' => 7,
                'payment_method_key' => 'ach_usd_withdraw',
                'actual_amount' => 745.00,
                'fee' => 5.00,
                'out_trade_no' => 'SOPAY' . strtoupper(Str::random(10)),
                'completed_at_offset' => 7,
            ],
            // 失败订单
            [
                'amount' => 300.00,
                'currency' => 'USD',
                'status' => Withdraw::STATUS_FAILED,
                'pay_status' => Withdraw::PAY_STATUS_FAILED,
                'approved' => false,
                'days_ago' => 3,
                'payment_method_key' => 'bank_transfer_usd_withdraw',
                'out_trade_no' => 'SOPAY' . strtoupper(Str::random(10)),
                'completed_at_offset' => 3,
            ],
            // 已取消订单
            [
                'amount' => 150.00,
                'currency' => 'USD',
                'status' => Withdraw::STATUS_CANCELLED,
                'pay_status' => Withdraw::PAY_STATUS_CANCELLED,
                'approved' => false,
                'days_ago' => 4,
                'payment_method_key' => 'ach_usd_withdraw',
                'completed_at_offset' => 4,
            ],
            // 已拒绝订单
            [
                'amount' => 250.00,
                'currency' => 'USD',
                'status' => Withdraw::STATUS_REJECTED,
                'pay_status' => Withdraw::PAY_STATUS_FAILED,
                'approved' => false,
                'days_ago' => 6,
                'payment_method_key' => 'bank_transfer_usd_withdraw',
                'note' => 'Withdrawal rejected due to insufficient verification',
            ],
        ];

        foreach ($withdraws as $withdrawData) {
            // 查找支付方式
            $paymentMethod = PaymentMethod::where('key', $withdrawData['payment_method_key'])->first();
            
            if (!$paymentMethod) {
                // 如果找不到指定的支付方式，使用第一个可用的支付方式
                $paymentMethod = $withdrawPaymentMethods->first();
            }

            // 生成订单号
            $orderNo = 'WTD' . strtoupper(Str::ulid()->toString());

            // 计算时间
            $createdAt = now()->subDays($withdrawData['days_ago']);
            
            $completedAt = isset($withdrawData['completed_at_offset'])
                ? now()->subDays($withdrawData['completed_at_offset'])
                : null;

            // 根据用户示例数据生成 withdraw_info 和 extra_info
            $withdrawInfo = [
                'ua' => 'h5',
                'aaid' => '',
                'client_id' => '',
                'android_id' => '',
                'channel_id' => 13,
            ];

            $extraInfo = [
                'ifsc' => 'IDJF0123123',
                'name' => 'sdfasdf',
                'email' => 'asdf@dfa.dd',
                'phone' => '1231232131',
                'bank_account' => '12312312312',
            ];

            Withdraw::updateOrCreate(
                ['order_no' => $orderNo],
                [
                    'user_id' => $user->id,
                    'out_trade_no' => $withdrawData['out_trade_no'] ?? null,
                    'currency' => $withdrawData['currency'],
                    'amount' => $withdrawData['amount'],
                    'actual_amount' => $withdrawData['actual_amount'] ?? null,
                    'payment_method_id' => $paymentMethod->id,
                    'withdraw_info' => isset($withdrawData['withdraw_info']) ? $withdrawData['withdraw_info'] : $withdrawInfo,
                    'extra_info' => isset($withdrawData['extra_info']) ? $withdrawData['extra_info'] : $extraInfo,
                    'status' => $withdrawData['status'],
                    'pay_status' => $withdrawData['pay_status'],
                    'fee' => $withdrawData['fee'] ?? 0.00,
                    'approved' => $withdrawData['approved'],
                    'user_ip' => '103.47.100.27',
                    'completed_at' => $completedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }

        $this->command->info('WithdrawSeeder completed successfully.');
    }
}

