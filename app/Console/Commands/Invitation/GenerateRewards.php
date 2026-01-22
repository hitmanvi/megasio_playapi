<?php

namespace App\Console\Commands\Invitation;

use App\Models\Invitation;
use App\Models\InvitationReward;
use App\Services\InvitationRewardService;
use App\Services\UserWagerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRewards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invitation:rewards:generate {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invitation rewards based on user wager for a specific date';

    protected UserWagerService $wagerService;
    protected InvitationRewardService $rewardService;

    public function __construct()
    {
        parent::__construct();
        $this->wagerService = new UserWagerService();
        $this->rewardService = new InvitationRewardService();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 获取日期参数，默认为昨天（因为今天的数据可能还在更新中）
        $date = $this->argument('date') ?: Carbon::yesterday()->format('Y-m-d');

        $this->info("Generating invitation rewards for date: {$date}");

        // 从 Redis 获取指定日期的所有用户的 wager 数据
        $wagers = $this->wagerService->getAllWagersByDate($date);

        if (empty($wagers)) {
            $this->info("No wager data found for date: {$date}");
            return Command::SUCCESS;
        }

        $this->info("Found " . count($wagers) . " users with wager data");

        $processed = 0;
        $skipped = 0;

        foreach ($wagers as $inviteeId => $wager) {
            // 查找该用户的邀请关系（作为被邀请人）
            $invitation = Invitation::where('invitee_id', $inviteeId)
                ->with(['inviter'])
                ->first();

            if (!$invitation) {
                // 该用户没有被邀请关系，跳过
                $skipped++;
                continue;
            }

            // 计算佣金（会判断游戏类型，如果不是 slot 类型返回 0）
            $rewardAmount = $this->rewardService->calculateReward($wager);

            if ($rewardAmount <= 0) {
                $skipped++;
                continue;
            }

            // 检查是否已经生成过该日期的奖励
            $existingReward = InvitationReward::where('invitation_id', $invitation->id)
                ->where('source_type', InvitationReward::SOURCE_TYPE_BET)
                ->whereDate('created_at', $date)
                ->first();

            if ($existingReward) {
                $this->warn("Reward already exists for invitation {$invitation->id} on {$date}");
                $skipped++;
                continue;
            }

            // 创建邀请奖励记录
            DB::transaction(function () use ($invitation, $wager, $rewardAmount, $date, $inviteeId) {
                InvitationReward::create([
                    'user_id' => $invitation->inviter_id, // 奖励给邀请人
                    'invitation_id' => $invitation->id,
                    'source_type' => InvitationReward::SOURCE_TYPE_BET,
                    'reward_type' => 'wager', // 可以根据需要调整
                    'reward_amount' => $rewardAmount,
                    'related_id' => null, // 可以根据需要存储相关ID
                ]);

                // 生成奖励后，删除 Redis 中的 wager 数据
                $this->wagerService->deleteWager($inviteeId, $date);
            });

            $processed++;
        }

        $this->info("Processed: {$processed}, Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
