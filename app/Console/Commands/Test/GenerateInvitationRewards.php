<?php

namespace App\Console\Commands\Test;

use App\Models\Invitation;
use App\Models\InvitationReward;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateInvitationRewards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:generate-invitation-rewards 
                            {user_id : 邀请人用户ID} 
                            {--count=10 : 每个邀请关系生成的奖励数量}
                            {--min-amount=1 : 最小奖励金额}
                            {--max-amount=100 : 最大奖励金额}
                            {--invitation-count=5 : 如果没有邀请关系，创建的邀请关系数量}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为指定用户的邀请关系生成测试奖励数据';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $count = (int) $this->option('count');
        $minAmount = (float) $this->option('min-amount');
        $maxAmount = (float) $this->option('max-amount');

        // 验证用户是否存在
        $user = User::find($userId);
        if (!$user) {
            $this->error("用户 ID {$userId} 不存在");
            return Command::FAILURE;
        }

        // 获取该用户的所有邀请关系
        $invitations = Invitation::where('inviter_id', $userId)
            ->with('invitee')
            ->get();

        // 如果没有邀请关系，自动创建一些
        if ($invitations->isEmpty()) {
            $invitationCount = (int) $this->option('invitation-count');
            $this->info("用户 {$user->name} (ID: {$userId}) 没有邀请关系，正在创建 {$invitationCount} 个邀请关系...");
            
            $invitations = $this->createInvitations($userId, $invitationCount);
            
            if ($invitations->isEmpty()) {
                $this->error("无法创建邀请关系，可能没有足够的可用用户");
                return Command::FAILURE;
            }
            
            $this->info("成功创建 {$invitations->count()} 个邀请关系！");
        } else {
            $this->info("找到 {$invitations->count()} 个邀请关系，开始生成测试奖励数据...");
        }

        $totalGenerated = 0;
        $sourceTypes = [
            InvitationReward::SOURCE_TYPE_DEPOSIT,
            InvitationReward::SOURCE_TYPE_BET,
            InvitationReward::SOURCE_TYPE_VIP,
        ];

        $bar = $this->output->createProgressBar($invitations->count() * $count);
        $bar->start();

        foreach ($invitations as $invitation) {
            for ($i = 0; $i < $count; $i++) {
                // 随机选择奖励来源类型
                $sourceType = $sourceTypes[array_rand($sourceTypes)];

                // 生成随机奖励金额
                $rewardAmount = $this->randomFloat($minAmount, $maxAmount);

                // 生成随机时间（最近30天内）
                $createdAt = Carbon::now()
                    ->subDays(rand(0, 30))
                    ->subHours(rand(0, 23))
                    ->subMinutes(rand(0, 59));

                DB::transaction(function () use ($invitation, $sourceType, $rewardAmount, $createdAt) {
                    InvitationReward::create([
                        'user_id' => $invitation->inviter_id,
                        'invitation_id' => $invitation->id,
                        'source_type' => $sourceType,
                        'reward_type' => config('app.currency', 'USD'),
                        'reward_amount' => $rewardAmount,
                        'related_id' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                });

                $totalGenerated++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("成功生成 {$totalGenerated} 条邀请奖励记录！");

        // 显示统计信息
        $stats = InvitationReward::where('user_id', $userId)
            ->selectRaw('source_type, COUNT(*) as count, SUM(reward_amount) as total')
            ->groupBy('source_type')
            ->get();

        if ($stats->isNotEmpty()) {
            $this->newLine();
            $this->info("奖励统计：");
            $tableData = $stats->map(function ($stat) {
                return [
                    'source_type' => $stat->source_type,
                    'count' => $stat->count,
                    'total_amount' => number_format((float) $stat->total, 2),
                ];
            })->toArray();

            $this->table(
                ['来源类型', '数量', '总金额'],
                $tableData
            );
        }

        return Command::SUCCESS;
    }

    /**
     * 创建邀请关系
     *
     * @param int $inviterId 邀请人ID
     * @param int $count 要创建的数量
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function createInvitations(int $inviterId, int $count)
    {
        // 获取已存在的被邀请人ID（invitee_id 必须是唯一的）
        $existingInviteeIds = Invitation::pluck('invitee_id')->toArray();
        
        // 添加邀请人自己（不能邀请自己）
        $existingInviteeIds[] = $inviterId;
        
        // 获取可用的用户（排除已经被邀请的用户和自己）
        $availableUsers = User::whereNotIn('id', $existingInviteeIds)
            ->inRandomOrder()
            ->limit($count)
            ->get();
        
        if ($availableUsers->isEmpty()) {
            return collect([]);
        }
        
        $createdInvitations = collect([]);
        
        DB::transaction(function () use ($inviterId, $availableUsers, &$createdInvitations) {
            foreach ($availableUsers as $invitee) {
                $invitation = Invitation::create([
                    'inviter_id' => $inviterId,
                    'invitee_id' => $invitee->id,
                ]);
                
                $createdInvitations->push($invitation);
            }
        });
        
        // 重新加载邀请关系（包含关联数据）
        return Invitation::where('inviter_id', $inviterId)
            ->whereIn('id', $createdInvitations->pluck('id'))
            ->with('invitee')
            ->get();
    }

    /**
     * 生成随机浮点数
     */
    protected function randomFloat(float $min, float $max): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 8);
    }
}
