<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Invitation;
use App\Models\InvitationReward;
use App\Models\Kyc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationRewardService
{
    protected BalanceService $balanceService;

    public function __construct()
    {
        $this->balanceService = new BalanceService();
    }
    /**
     * 计算佣金
     * 如果不是 slot 类型游戏，返回 0
     *
     * @param float $wager wager 金额
     * @param Game|null $game 游戏对象（可选，用于判断游戏类型）
     * @return float 佣金金额，如果不是 slot 类型或佣金为 0，返回 0
     */
    public function calculateReward(float $wager, ?Game $game = null): float
    {
        // 如果提供了游戏对象，检查是否是 slot 类型
        if ($game !== null && !$this->isSlotGame($game)) {
            return 0.0;
        }

        // 佣金的1% * 佣金奖励比例
        $settingService = new SettingService();
        $commissionBonus = $settingService->getValue('commission_bonus');

        // 检查配置是否存在且已启用
        if (!$commissionBonus || !isset($commissionBonus['enabled']) || !$commissionBonus['enabled']) {
            return 0.0;
        }

        if (!isset($commissionBonus['ratio'])) {
            return 0.0;
        }

        $reward = $wager / 100 * $commissionBonus['ratio'] / 100;

        return (float) $reward;
    }

    /**
     * 检查游戏是否是 slot 类型
     *
     * @param Game $game
     * @return bool
     */
    protected function isSlotGame(Game $game): bool
    {
        if (!$game->category_id || !$game->category) {
            return false;
        }

        // 通过 category 的 name 判断是否是 slot
        // 假设 slot 类型的 category name 包含 "slot" 或 "Slot"
        $categoryName = $game->category->getName();
        if (!$categoryName) {
            return false;
        }

        return Str::contains(strtolower($categoryName), 'slot');
    }

    /**
     * 检查邀请方和被邀请人的 KYC 状态并创建奖励
     * 如果双方 KYC 都已认证，则自动发放；否则设置为未发放状态
     *
     * @param Invitation $invitation
     * @param array $rewardData 奖励数据
     * @return InvitationReward
     */
    public function createRewardWithKycCheck(Invitation $invitation, array $rewardData): InvitationReward
    {
        // 检查被邀请人的 KYC 状态
        $invitee = $invitation->invitee;
        $inviteeKyc = Kyc::where('user_id', $invitee->id)->first();
        $isInviteeKycVerified = $inviteeKyc && $inviteeKyc->isVerified();

        // 检查邀请方的 KYC 状态
        $inviter = $invitation->inviter;
        $inviterKyc = Kyc::where('user_id', $inviter->id)->first();
        $isInviterKycVerified = $inviterKyc && $inviterKyc->isVerified();

        // 只有双方 KYC 都已认证才能发放
        $isKycVerified = $isInviteeKycVerified && $isInviterKycVerified;

        // 设置默认状态
        $rewardData['status'] = $isKycVerified 
            ? InvitationReward::STATUS_PAID 
            : InvitationReward::STATUS_PENDING;

        return DB::transaction(function () use ($invitation, $rewardData, $isKycVerified) {
            // 创建邀请奖励记录
            $reward = InvitationReward::create($rewardData);

            // 如果双方 KYC 都已认证，立即发放奖励
            if ($isKycVerified) {
                $this->balanceService->invitationReward(
                    $invitation->inviter_id,
                    $reward->reward_type,
                    $reward->reward_amount,
                    $reward->id,
                    $reward->related_id ?? 'invitation_reward'
                );
            }

            return $reward;
        });
    }

    /**
     * 发放未发放的奖励
     *
     * @param InvitationReward $reward
     * @return bool
     */
    public function payReward(InvitationReward $reward): bool
    {
        if ($reward->isPaid()) {
            return false; // 已经发放过了
        }

        return DB::transaction(function () use ($reward) {
            // 发放奖励
            $this->balanceService->invitationReward(
                $reward->user_id,
                $reward->reward_type,
                $reward->reward_amount,
                $reward->id,
                $reward->related_id ?? 'invitation_reward'
            );

            // 更新状态为已发放
            $reward->status = InvitationReward::STATUS_PAID;
            $reward->save();

            return true;
        });
    }

    /**
     * 为指定用户发放所有未发放的邀请奖励
     * 需要同时检查邀请方和被邀请方的 KYC 状态
     *
     * @param int $userId 被邀请人用户ID
     * @return int 发放的奖励数量
     */
    public function payPendingRewardsForUser(int $userId): int
    {
        // 查找该用户作为被邀请人的所有未发放奖励
        $invitation = Invitation::where('invitee_id', $userId)
            ->with(['inviter'])
            ->first();
        
        if (!$invitation) {
            return 0;
        }

        // 检查被邀请人的 KYC 状态
        $inviteeKyc = Kyc::where('user_id', $userId)->first();
        $isInviteeKycVerified = $inviteeKyc && $inviteeKyc->isVerified();

        // 检查邀请方的 KYC 状态
        $inviterKyc = Kyc::where('user_id', $invitation->inviter_id)->first();
        $isInviterKycVerified = $inviterKyc && $inviterKyc->isVerified();

        // 只有双方 KYC 都已认证才能发放
        if (!$isInviteeKycVerified || !$isInviterKycVerified) {
            return 0;
        }

        $pendingRewards = InvitationReward::where('invitation_id', $invitation->id)
            ->where('status', InvitationReward::STATUS_PENDING)
            ->get();

        $paidCount = 0;
        foreach ($pendingRewards as $reward) {
            if ($this->payReward($reward)) {
                $paidCount++;
            }
        }

        return $paidCount;
    }
}
