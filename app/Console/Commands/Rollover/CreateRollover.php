<?php

namespace App\Console\Commands\Rollover;

use App\Models\Rollover;
use App\Models\User;
use Illuminate\Console\Command;

class CreateRollover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rollover:create 
                            {user_id : 用户ID}
                            {--currency=USD : 货币类型}
                            {--amount=100 : 金额}
                            {--source_type=deposit : 来源类型 (deposit, bonus, reward)}
                            {--related_id=0 : 关联的订单ID}
                            {--required_wager= : 需要的流水（默认与金额相同）}
                            {--status=pending : 初始状态 (pending, active, completed)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为指定用户创建 rollover 记录';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $currency = $this->option('currency');
        $amount = (float) $this->option('amount');
        $sourceType = $this->option('source_type');
        $relatedId = (int) $this->option('related_id');
        $requiredWager = $this->option('required_wager') !== null 
            ? (float) $this->option('required_wager') 
            : $amount;
        $status = $this->option('status');

        // 验证用户是否存在
        $user = User::find($userId);
        if (!$user) {
            $this->error("用户 ID {$userId} 不存在");
            return Command::FAILURE;
        }

        // 验证 source_type
        $validSourceTypes = [
            Rollover::SOURCE_TYPE_DEPOSIT,
            Rollover::SOURCE_TYPE_BONUS,
            Rollover::SOURCE_TYPE_REWARD,
        ];
        if (!in_array($sourceType, $validSourceTypes)) {
            $this->error("无效的 source_type: {$sourceType}。有效值: " . implode(', ', $validSourceTypes));
            return Command::FAILURE;
        }

        // 验证 status
        $validStatuses = [
            Rollover::STATUS_PENDING,
            Rollover::STATUS_ACTIVE,
            Rollover::STATUS_COMPLETED,
        ];
        if (!in_array($status, $validStatuses)) {
            $this->error("无效的 status: {$status}。有效值: " . implode(', ', $validStatuses));
            return Command::FAILURE;
        }

        // 验证金额
        if ($amount <= 0) {
            $this->error("金额必须大于 0");
            return Command::FAILURE;
        }

        // 验证 required_wager
        if ($requiredWager < 0) {
            $this->error("需要的流水不能为负数");
            return Command::FAILURE;
        }

        // 如果没有其他激活的 rollover，且状态为 pending，则自动激活
        if ($status === Rollover::STATUS_PENDING) {
            $hasActiveRollover = Rollover::where('user_id', $userId)
                ->where('currency', $currency)
                ->where('status', Rollover::STATUS_ACTIVE)
                ->exists();

            if (!$hasActiveRollover) {
                $status = Rollover::STATUS_ACTIVE;
                $this->info("检测到该用户没有激活的 rollover，自动将状态设置为 active");
            }
        }

        // 创建 rollover
        try {
            $rollover = Rollover::create([
                'user_id' => $userId,
                'source_type' => $sourceType,
                'related_id' => $relatedId > 0 ? $relatedId : null,
                'currency' => $currency,
                'amount' => $amount,
                'required_wager' => $requiredWager,
                'current_wager' => 0,
                'status' => $status,
            ]);

            $this->info("成功创建 rollover 记录:");
            $this->table(
                ['字段', '值'],
                [
                    ['ID', $rollover->id],
                    ['用户ID', $rollover->user_id],
                    ['来源类型', $rollover->source_type],
                    ['关联ID', $rollover->related_id ?? 'N/A'],
                    ['货币', $rollover->currency],
                    ['金额', $rollover->amount],
                    ['需要流水', $rollover->required_wager],
                    ['当前流水', $rollover->current_wager],
                    ['状态', $rollover->status],
                    ['进度', $rollover->getProgressPercent() . '%'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("创建 rollover 失败: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
