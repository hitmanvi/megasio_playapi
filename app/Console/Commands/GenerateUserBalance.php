<?php

namespace App\Console\Commands;

use App\Models\Balance;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateUserBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balance:generate 
                            {user_id : 用户ID}
                            {--currency= : 货币代码（如不指定，则为所有启用的货币创建余额）}
                            {--available=0 : 可用余额}
                            {--frozen=0 : 冻结余额}
                            {--force : 如果余额已存在则更新}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为指定用户生成余额记录';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = (int) $this->argument('user_id');
        $currency = $this->option('currency');
        $available = (float) $this->option('available');
        $frozen = (float) $this->option('frozen');
        $force = $this->option('force');

        // 检查用户是否存在
        $user = User::find($userId);
        if (!$user) {
            $this->error("用户 ID {$userId} 不存在");
            return Command::FAILURE;
        }

        $this->info("为用户 {$user->name} (ID: {$userId}) 生成余额...");

        // 确定要处理的货币列表
        $currencies = [];
        if ($currency) {
            $currencyModel = Currency::where('code', $currency)->first();
            if (!$currencyModel) {
                $this->error("货币代码 '{$currency}' 不存在");
                return Command::FAILURE;
            }
            $currencies = [$currencyModel];
        } else {
            $currencies = Currency::enabled()->ordered()->get();
            if ($currencies->isEmpty()) {
                $this->error('没有启用的货币');
                return Command::FAILURE;
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($currencies as $currencyModel) {
                $currencyCode = $currencyModel->code;

                // 检查余额是否已存在
                $existingBalance = Balance::where('user_id', $userId)
                    ->where('currency', $currencyCode)
                    ->first();

                if ($existingBalance) {
                    if ($force) {
                        $existingBalance->update([
                            'available' => $available,
                            'frozen' => $frozen,
                            'version' => 0, // 重置版本号
                        ]);
                        $updated++;
                        $this->line("  ✓ 更新货币 {$currencyCode} 的余额: 可用={$available}, 冻结={$frozen}");
                    } else {
                        $skipped++;
                        $this->warn("  - 跳过货币 {$currencyCode}（余额已存在，使用 --force 强制更新）");
                    }
                    continue;
                }

                // 创建新余额
                Balance::create([
                    'user_id' => $userId,
                    'currency' => $currencyCode,
                    'available' => $available,
                    'frozen' => $frozen,
                    'version' => 0,
                ]);

                $created++;
                $this->line("  ✓ 创建货币 {$currencyCode} 的余额: 可用={$available}, 冻结={$frozen}");
            }

            DB::commit();

            $this->newLine();
            $this->info("完成！创建: {$created}, 更新: {$updated}, 跳过: {$skipped}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("生成余额时出错: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
