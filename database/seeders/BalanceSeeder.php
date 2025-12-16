<?php

namespace Database\Seeders;

use App\Models\Balance;
use App\Models\Transaction;
use App\Services\BalanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BalanceSeeder extends Seeder
{
    protected BalanceService $balanceService;

    public function __construct()
    {
        $this->balanceService = app(BalanceService::class);
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = 1;

        // 创建不同货币的余额
        $currencies = ['USD', 'EUR', 'CNY', 'JPY'];
        $balances = [
            'USD' => ['available' => 5000.00000000, 'frozen' => 500.00000000],
            'EUR' => ['available' => 3000.00000000, 'frozen' => 200.00000000],
            'CNY' => ['available' => 10000.00000000, 'frozen' => 1000.00000000],
            'JPY' => ['available' => 200000.00000000, 'frozen' => 10000.00000000],
        ];

        foreach ($currencies as $currency) {
            // 检查余额是否存在
            $balance = Balance::where('user_id', $userId)
                             ->where('currency', $currency)
                             ->first();

            if (!$balance) {
                // 创建余额记录
                Balance::create([
                    'user_id' => $userId,
                    'currency' => $currency,
                    'available' => $balances[$currency]['available'],
                    'frozen' => $balances[$currency]['frozen'],
                    'version' => 0,
                ]);

                // 创建初始存款交易
                Transaction::create([
                    'user_id' => $userId,
                    'currency' => $currency,
                    'amount' => $balances[$currency]['available'] + $balances[$currency]['frozen'],
                    'type' => Transaction::TYPE_DEPOSIT,
                    'status' => Transaction::STATUS_COMPLETED,
                    'notes' => "Initial balance deposit for {$currency}",
                    'transaction_time' => now()->subDays(30),
                ]);
            }
        }

        // 创建一些模拟交易记录
        $this->createSampleTransactions($userId);
    }

    /**
     * 创建模拟交易记录
     */
    private function createSampleTransactions(int $userId): void
    {
        $transactions = [
            // USD 交易记录
            [
                'currency' => 'USD',
                'amount' => 1000.00000000,
                'type' => Transaction::TYPE_DEPOSIT,
                'notes' => 'Bank deposit USD',
                'days_ago' => 15,
            ],
            [
                'currency' => 'USD',
                'amount' => -500.00000000,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'notes' => 'Withdrawal USD',
                'days_ago' => 10,
            ],
            [
                'currency' => 'USD',
                'amount' => 2000.00000000,
                'type' => Transaction::TYPE_DEPOSIT,
                'notes' => 'Refund USD',
                'days_ago' => 5,
            ],
            // EUR 交易记录
            [
                'currency' => 'EUR',
                'amount' => 500.00000000,
                'type' => Transaction::TYPE_DEPOSIT,
                'notes' => 'Bank deposit EUR',
                'days_ago' => 20,
            ],
            [
                'currency' => 'EUR',
                'amount' => -200.00000000,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'notes' => 'Withdrawal EUR',
                'days_ago' => 12,
            ],
            // CNY 交易记录
            [
                'currency' => 'CNY',
                'amount' => 5000.00000000,
                'type' => Transaction::TYPE_DEPOSIT,
                'notes' => 'Bank deposit CNY',
                'days_ago' => 25,
            ],
            [
                'currency' => 'CNY',
                'amount' => -1000.00000000,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'notes' => 'Withdrawal CNY',
                'days_ago' => 8,
            ],
            // JPY 交易记录
            [
                'currency' => 'JPY',
                'amount' => 100000.00000000,
                'type' => Transaction::TYPE_DEPOSIT,
                'notes' => 'Bank deposit JPY',
                'days_ago' => 18,
            ],
            [
                'currency' => 'JPY',
                'amount' => -50000.00000000,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'notes' => 'Withdrawal JPY',
                'days_ago' => 14,
            ],
        ];

        foreach ($transactions as $transaction) {
            // 检查交易是否已存在（避免重复）
            $exists = Transaction::where('user_id', $userId)
                                ->where('currency', $transaction['currency'])
                                ->where('amount', $transaction['amount'])
                                ->where('type', $transaction['type'])
                                ->exists();

            if (!$exists) {
                Transaction::create([
                    'user_id' => $userId,
                    'currency' => $transaction['currency'],
                    'amount' => $transaction['amount'],
                    'type' => $transaction['type'],
                    'status' => Transaction::STATUS_COMPLETED,
                    'notes' => $transaction['notes'],
                    'transaction_time' => now()->subDays($transaction['days_ago']),
                ]);
            }
        }
    }
}
