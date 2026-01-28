<?php

namespace App\Console\Commands\Invitation;

use App\Models\Invitation;
use App\Models\InvitationReward;
use App\Services\BalanceService;
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
    protected BalanceService $balanceService;

    public function __construct()
    {
        parent::__construct();
        $this->wagerService = new UserWagerService();
        $this->rewardService = new InvitationRewardService();
        $this->balanceService = new BalanceService();
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

            $currency = config('app.currency', 'USD');

            // 创建邀请奖励记录并更新余额（模型事件会自动更新 total_reward）
            DB::transaction(function () use ($invitation, $wager, $rewardAmount, $date, $inviteeId, $currency) {
                // 创建邀请奖励记录
                $reward = InvitationReward::create([
                    'user_id' => $invitation->inviter_id, // 奖励给邀请人
                    'invitation_id' => $invitation->id,
                    'source_type' => InvitationReward::SOURCE_TYPE_BET,
                    'reward_type' => $currency,
                    'reward_amount' => $rewardAmount,
                    'wager' => $wager, // 记录下注金额
                    'related_id' => null, // 可以根据需要存储相关ID
                ]);

                // 使用 BalanceService 增加邀请人的余额并创建交易记录
                $this->balanceService->invitationReward(
                    $invitation->inviter_id,
                    $currency,
                    $rewardAmount,
                    $reward->id,
                    'bet_' . $date // 使用日期作为奖励类型标识
                );

                // 生成奖励后，删除 Redis 中的 wager 数据
                $this->wagerService->deleteWager($inviteeId, $date);
            });

            $processed++;
        }

        $this->info("Processed: {$processed}, Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
