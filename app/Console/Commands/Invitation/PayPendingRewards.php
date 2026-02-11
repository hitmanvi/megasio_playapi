<?php

namespace App\Console\Commands\Invitation;

use App\Models\Invitation;
use App\Models\InvitationReward;
use App\Models\Kyc;
use App\Services\InvitationRewardService;
use Illuminate\Console\Command;

class PayPendingRewards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invitation:rewards:pay-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pay pending invitation rewards for users who have completed KYC verification';

    protected InvitationRewardService $rewardService;

    public function __construct()
    {
        parent::__construct();
        $this->rewardService = new InvitationRewardService();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to pay pending invitation rewards...');

        // 查找所有有未发放奖励的邀请关系
        $invitationsWithPendingRewards = Invitation::whereHas('rewards', function ($query) {
            $query->where('status', InvitationReward::STATUS_PENDING);
        })->with(['invitee', 'inviter'])->get();

        if ($invitationsWithPendingRewards->isEmpty()) {
            $this->info('No invitations with pending rewards found.');
            return Command::SUCCESS;
        }

        $this->info("Found " . $invitationsWithPendingRewards->count() . " invitations with pending rewards");

        $processed = 0;
        $paid = 0;
        $skipped = 0;

        foreach ($invitationsWithPendingRewards as $invitation) {
            $invitee = $invitation->invitee;
            $inviter = $invitation->inviter;
            
            if (!$invitee) {
                $this->warn("Invitation {$invitation->id} has no invitee, skipping...");
                $skipped++;
                continue;
            }

            if (!$inviter) {
                $this->warn("Invitation {$invitation->id} has no inviter, skipping...");
                $skipped++;
                continue;
            }

            // 检查被邀请人的 KYC 状态
            $inviteeKyc = Kyc::where('user_id', $invitee->id)->first();
            $isInviteeKycVerified = $inviteeKyc && $inviteeKyc->isVerified();

            // 检查邀请方的 KYC 状态
            $inviterKyc = Kyc::where('user_id', $inviter->id)->first();
            $isInviterKycVerified = $inviterKyc && $inviterKyc->isVerified();

            // 只有双方 KYC 都已认证才能发放
            if (!$isInviteeKycVerified || !$isInviterKycVerified) {
                $skipped++;
                continue;
            }

            // 双方 KYC 都已认证，发放未发放的奖励
            $paidCount = $this->rewardService->payPendingRewardsForUser($invitee->id);
            
            if ($paidCount > 0) {
                $this->info("Paid {$paidCount} reward(s) for user {$invitee->id} (invitation {$invitation->id})");
                $paid += $paidCount;
            }
            
            $processed++;
        }

        $this->info("Processed: {$processed}, Paid: {$paid} reward(s), Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
