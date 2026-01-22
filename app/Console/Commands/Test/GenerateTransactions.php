<?php

namespace App\Console\Commands\Test;

use App\Models\Deposit;
use App\Models\Game;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ProviderTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GenerateTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:generate-transactions 
                            {user_id : 用户ID} 
                            {count=10 : 生成条数}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成测试交易数据（自动创建所需的关联实体）';

    protected Collection $deposits;
    protected Collection $withdraws;
    protected Collection $providerTransactions;
    protected Collection $games;
    protected Collection $paymentMethods;
    protected int $userId;

    /**
     * 货币列表
     */
    protected array $currencies = ['USD', 'EUR', 'USDT'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->userId = (int) $this->argument('user_id');
        $count = (int) $this->argument('count');

        // 验证用户是否存在
        $user = User::find($this->userId);
        if (!$user) {
            $this->error("用户 ID {$this->userId} 不存在");
            return 1;
        }

        // 加载游戏数据
        $this->games = Game::where('enabled', true)->get();
        if ($this->games->isEmpty()) {
            $this->error("没有可用的游戏数据，请先同步游戏");
            return 1;
        }

        // 加载支付方式
        $this->paymentMethods = PaymentMethod::where('enabled', true)->get();
        if ($this->paymentMethods->isEmpty()) {
            $this->error("没有可用的支付方式，请先创建支付方式");
            return 1;
        }

        // 加载或生成实体数据
        $this->loadOrCreateEntities();

        $this->info("实体数据: 存款({$this->deposits->count()}) 提款({$this->withdraws->count()}) 游戏订单({$this->providerTransactions->count()})");
        $this->info("开始为用户 {$user->name} (ID: {$this->userId}) 生成 {$count} 条交易记录...");

        // 构建可用类型
        $availableTypes = $this->buildAvailableTypes();

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $generated = 0;
        for ($i = 0; $i < $count; $i++) {
            $type = $this->getRandomType($availableTypes);
            $this->createTransaction($type);
            $generated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("成功生成 {$generated} 条交易记录");

        return 0;
    }

    /**
     * 加载或创建实体数据
     */
    protected function loadOrCreateEntities(): void
    {
        // 加载现有数据
        $this->deposits = Deposit::where('user_id', $this->userId)->get();
        $this->withdraws = Withdraw::where('user_id', $this->userId)->get();
        $this->providerTransactions = ProviderTransaction::where('user_id', $this->userId)
            ->whereNotNull('order_id')
            ->with('order')
            ->get();

        // 如果没有存款，创建测试存款
        if ($this->deposits->isEmpty()) {
            $this->info("未找到存款记录，正在创建测试存款...");
            $this->createTestDeposits(5);
            $this->deposits = Deposit::where('user_id', $this->userId)->get();
        }

        // 如果没有提款，创建测试提款
        if ($this->withdraws->isEmpty()) {
            $this->info("未找到提款记录，正在创建测试提款...");
            $this->createTestWithdraws(3);
            $this->withdraws = Withdraw::where('user_id', $this->userId)->get();
        }

        // 如果没有游戏订单，创建测试订单
        if ($this->providerTransactions->isEmpty()) {
            $this->info("未找到游戏订单，正在创建测试订单...");
            $this->createTestOrders(10);
            $this->providerTransactions = ProviderTransaction::where('user_id', $this->userId)
                ->whereNotNull('order_id')
                ->with('order')
                ->get();
        }
    }

    /**
     * 创建测试存款记录
     */
    protected function createTestDeposits(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $paymentMethod = $this->paymentMethods->random();
            $currency = $paymentMethod->currency ?? $this->currencies[array_rand($this->currencies)];
            $amount = $this->randomFloat(50, 1000);

            Deposit::create([
                'user_id' => $this->userId,
                'order_no' => 'D' . date('YmdHis') . str_pad($i, 4, '0', STR_PAD_LEFT),
                'out_trade_no' => 'OUT' . Str::random(16),
                'currency' => $currency,
                'amount' => $amount,
                'actual_amount' => $amount,
                'payment_method_id' => $paymentMethod->id,
                'status' => Deposit::STATUS_COMPLETED,
                'pay_status' => Deposit::PAY_STATUS_PAID,
                'finished_at' => now()->subDays(rand(1, 30)),
            ]);
        }
    }

    /**
     * 创建测试提款记录
     */
    protected function createTestWithdraws(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $paymentMethod = $this->paymentMethods->random();
            $currency = $paymentMethod->currency ?? $this->currencies[array_rand($this->currencies)];
            $amount = $this->randomFloat(20, 500);

            Withdraw::create([
                'user_id' => $this->userId,
                'order_no' => 'W' . date('YmdHis') . str_pad($i, 4, '0', STR_PAD_LEFT),
                'out_trade_no' => 'OUT' . Str::random(16),
                'currency' => $currency,
                'amount' => $amount,
                'actual_amount' => $amount,
                'payment_method_id' => $paymentMethod->id,
                'status' => Withdraw::STATUS_COMPLETED,
                'pay_status' => Withdraw::PAY_STATUS_PAID,
                'approved' => true,
                'completed_at' => now()->subDays(rand(1, 30)),
            ]);
        }
    }

    /**
     * 创建测试游戏订单
     */
    protected function createTestOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $game = $this->games->random();
            $currency = $this->currencies[array_rand($this->currencies)];
            $amount = $this->randomFloat(1, 100);
            $payout = rand(0, 1) ? $this->randomFloat(0, $amount * 3) : 0;
            $roundId = Str::ulid()->toString();

            // 创建订单
            $order = Order::create([
                'user_id' => $this->userId,
                'game_id' => $game->id,
                'brand_id' => $game->brand_id,
                'order_id' => Str::ulid()->toString(),
                'out_id' => $roundId,
                'currency' => $currency,
                'amount' => $amount,
                'payout' => $payout,
                'status' => Order::STATUS_COMPLETED,
                'finished_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
            ]);

            // 创建 ProviderTransaction
            ProviderTransaction::create([
                'provider' => $game->brand->provider ?? 'test',
                'game_id' => $game->id,
                'user_id' => $this->userId,
                'order_id' => $order->id,
                'txid' => 'TX' . Str::random(16),
                'round_id' => $roundId,
                'detail' => ['test' => true],
            ]);
        }
    }

    /**
     * 构建可用的交易类型及权重
     */
    protected function buildAvailableTypes(): array
    {
        return [
            Transaction::TYPE_DEPOSIT => 15,
            Transaction::TYPE_WITHDRAWAL => 10,
            Transaction::TYPE_WITHDRAWAL_UNFREEZE => 5,
            Transaction::TYPE_BET => 40,
            Transaction::TYPE_PAYOUT => 25,
            Transaction::TYPE_REFUND => 5,
        ];
    }

    /**
     * 根据权重随机获取交易类型
     */
    protected function getRandomType(array $types): string
    {
        $totalWeight = array_sum($types);
        $random = rand(1, $totalWeight);
        $current = 0;

        foreach ($types as $type => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $type;
            }
        }

        return array_key_first($types);
    }

    /**
     * 创建单条交易记录
     */
    protected function createTransaction(string $type): Transaction
    {
        $entityData = $this->getEntityData($type);

        // 生成随机时间（最近30天内）
        $transactionTime = now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

        return Transaction::create([
            'user_id' => $this->userId,
            'currency' => $entityData['currency'],
            'amount' => $entityData['amount'],
            'type' => $type,
            'status' => Transaction::STATUS_COMPLETED,
            'related_entity_id' => $entityData['related_entity_id'],
            'notes' => $this->generateNotes($type),
            'transaction_time' => $transactionTime,
        ]);
    }

    /**
     * 获取实体数据
     */
    protected function getEntityData(string $type): array
    {
        return match ($type) {
            Transaction::TYPE_DEPOSIT => $this->getDepositData(),
            Transaction::TYPE_WITHDRAWAL, Transaction::TYPE_WITHDRAWAL_UNFREEZE => $this->getWithdrawData(),
            Transaction::TYPE_BET, Transaction::TYPE_PAYOUT, Transaction::TYPE_REFUND => $this->getOrderData($type),
            default => $this->getDepositData(),
        };
    }

    /**
     * 获取存款数据
     */
    protected function getDepositData(): array
    {
        $deposit = $this->deposits->random();

        // 添加随机后缀避免唯一约束冲突
        $relatedEntityId = $deposit->id . '_' . Str::random(6);

        return [
            'currency' => $deposit->currency,
            'amount' => (float) $deposit->amount,
            'related_entity_id' => $relatedEntityId,
        ];
    }

    /**
     * 获取提款数据
     */
    protected function getWithdrawData(): array
    {
        $withdraw = $this->withdraws->random();

        // 添加随机后缀避免唯一约束冲突
        $relatedEntityId = $withdraw->id . '_' . Str::random(6);

        return [
            'currency' => $withdraw->currency,
            'amount' => (float) $withdraw->amount,
            'related_entity_id' => $relatedEntityId,
        ];
    }

    /**
     * 获取订单数据
     */
    protected function getOrderData(string $type): array
    {
        $providerTx = $this->providerTransactions->random();
        $order = $providerTx->order;

        // 根据类型决定金额
        $amount = match ($type) {
            Transaction::TYPE_BET => (float) $order->amount,
            Transaction::TYPE_PAYOUT => (float) $order->payout,
            Transaction::TYPE_REFUND => (float) $order->amount,
            default => (float) $order->amount,
        };

        // related_entity_id 格式: gameId_txid
        // 使用唯一的 txid 避免重复
        $uniqueTxid = $providerTx->txid . '_' . Str::random(8);
        $relatedEntityId = $providerTx->game_id . '_' . $uniqueTxid;

        return [
            'currency' => $order->currency,
            'amount' => $amount,
            'related_entity_id' => $relatedEntityId,
        ];
    }

    /**
     * 生成随机浮点数
     */
    protected function randomFloat(float $min, float $max): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 8);
    }

    /**
     * 生成备注
     */
    protected function generateNotes(string $type): ?string
    {
        $notes = [
            Transaction::TYPE_DEPOSIT => ['Bank transfer', 'Crypto deposit', 'Card payment', null],
            Transaction::TYPE_WITHDRAWAL => ['Withdrawal request', 'Bank withdrawal', null],
            Transaction::TYPE_WITHDRAWAL_UNFREEZE => ['Withdrawal completed', null],
            Transaction::TYPE_BET => [null],
            Transaction::TYPE_PAYOUT => [null],
            Transaction::TYPE_REFUND => ['Game cancelled', 'Refund processed', null],
        ];

        $typeNotes = $notes[$type] ?? [null];
        return $typeNotes[array_rand($typeNotes)];
    }
}
