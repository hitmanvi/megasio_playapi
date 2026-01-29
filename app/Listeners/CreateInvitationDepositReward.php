<?php

namespace App\Listeners;

use App\Events\DepositCompleted;
use App\Models\Deposit;
use App\Models\Invitation;
use App\Models\InvitationReward;
use App\Services\BalanceService;
use App\Services\SettingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class CreateInvitationDepositReward implements ShouldQueue
{
    use InteractsWithQueue;

    protected SettingService $settingService;
    protected BalanceService $balanceService;

    public function __construct()
    {
        $this->settingService = new SettingService();
        $this->balanceService = new BalanceService();
    }

    /**
     * Handle the event.
     */
    public function handle(DepositCompleted $event): void
    {
        $deposit = $event->deposit;

        // 查找该用户的邀请关系（作为被邀请人）
        $invitation = Invitation::where('invitee_id', $deposit->user_id)
            ->with(['inviter'])
            ->first();

        if (!$invitation) {
            // 该用户没有被邀请关系，跳过
            return;
        }

        // 计算用户的总充值金额（所有已完成的充值）
        $totalDepositAmount = Deposit::where('user_id', $deposit->user_id)
            ->where('status', Deposit::STATUS_COMPLETED)
            ->sum('amount');

        $totalDepositAmount = (float) $totalDepositAmount;

        // 检查并发放高级奖励（如果满足条件且还没给过）
        $this->checkAndCreateReward($invitation, $totalDepositAmount, 'deposit_bonus_advanced');

        // 检查并发放新手奖励（如果满足条件且还没给过）
        $this->checkAndCreateReward($invitation, $totalDepositAmount, 'deposit_bonus_starter');
    }

    /**
     * 检查并创建奖励
     *
     * @param Invitation $invitation
     * @param float $totalDepositAmount 用户总充值金额
     * @param string $rewardTypeKey 奖励类型标识
     * @return void
     */
    protected function checkAndCreateReward(Invitation $invitation, float $totalDepositAmount, string $rewardTypeKey): void
    {
        // 检查是否已经给过该类型的奖励
        $existingReward = InvitationReward::where('invitation_id', $invitation->id)
            ->where('source_type', InvitationReward::SOURCE_TYPE_DEPOSIT)
            ->where('related_id', $rewardTypeKey)
            ->first();

        if ($existingReward) {
            // 已经给过该类型的奖励，跳过
            return;
        }

        // 获取奖励配置
        $bonusConfig = $this->settingService->getValue($rewardTypeKey);

        // 检查配置是否存在且已启用
        if (!$bonusConfig || !isset($bonusConfig['enabled']) || !$bonusConfig['enabled']) {
            // 配置不存在或未启用，跳过
            return;
        }

        if (!isset($bonusConfig['deposit_min_amount']) || !isset($bonusConfig['bonus_amount'])) {
            // 配置不完整，跳过
            return;
        }

        $minAmount = (float) $bonusConfig['deposit_min_amount'];

        // 检查总充值金额是否达到阈值
        if ($totalDepositAmount < $minAmount) {
            // 总充值金额不满足条件，跳过
            return;
        }

        $currency = $bonusConfig['currency'] ?? config('app.currency', 'USD');
        $rewardAmount = (float) $bonusConfig['bonus_amount'];

        // 创建邀请奖励记录并更新余额（模型事件会自动更新 total_reward）
        DB::transaction(function () use ($invitation, $bonusConfig, $rewardTypeKey, $currency, $rewardAmount) {
            // 创建邀请奖励记录
            $reward = InvitationReward::create([
                'user_id' => $invitation->inviter_id, // 奖励给邀请人
                'invitation_id' => $invitation->id,
                'source_type' => InvitationReward::SOURCE_TYPE_DEPOSIT,
                'reward_type' => $currency,
                'reward_amount' => $rewardAmount,
                'wager' => 0, // 充值奖励没有 wager
                'related_id' => $rewardTypeKey, // 奖励类型标识（deposit_bonus_starter 或 deposit_bonus_advanced）
            ]);

            // 使用 BalanceService 增加邀请人的余额并创建交易记录
            $this->balanceService->invitationReward(
                $invitation->inviter_id,
                $currency,
                $rewardAmount,
                $reward->id,
                $rewardTypeKey
            );
        });
    }
}
