<?php

namespace App\Listeners;

use App\Events\VipLevelUpgraded;
use App\Models\Invitation;
use App\Models\InvitationReward;
use App\Models\VipLevel;
use App\Services\InvitationRewardService;
use App\Services\SettingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class CreateInvitationVipReward implements ShouldQueue
{
    use InteractsWithQueue;

    protected SettingService $settingService;
    protected InvitationRewardService $rewardService;

    public function __construct()
    {
        $this->settingService = new SettingService();
        $this->rewardService = new InvitationRewardService();
    }

    /**
     * Handle the event.
     */
    public function handle(VipLevelUpgraded $event): void
    {
        $user = $event->user;
        $oldLevel = $event->oldLevel;
        $newLevel = $event->newLevel;

        // 查找该用户的邀请关系（作为被邀请人）
        $invitation = Invitation::where('invitee_id', $user->id)
            ->with(['inviter'])
            ->first();

        if (!$invitation) {
            // 该用户没有被邀请关系，跳过
            return;
        }

        // 获取 VIP 升级奖励配置
        $vipUpgradeConfig = $this->settingService->getValue('vip_upgrade_bonus');

        // 检查配置是否存在且已启用
        if (!$vipUpgradeConfig || !isset($vipUpgradeConfig['enabled']) || !$vipUpgradeConfig['enabled']) {
            // 配置不存在或未启用，跳过
            return;
        }

        if (!isset($vipUpgradeConfig['levels']) || !is_array($vipUpgradeConfig['levels'])) {
            // 配置不完整，跳过
            return;
        }

        // 获取所有等级列表（按顺序）
        $allLevels = VipLevel::getLevelKeys();

        // 找到 oldLevel 和 newLevel 在列表中的位置
        $oldLevelIndex = array_search($oldLevel, $allLevels);
        $newLevelIndex = array_search($newLevel, $allLevels);

        if ($oldLevelIndex === false || $newLevelIndex === false || $oldLevelIndex >= $newLevelIndex) {
            // 等级不在列表中或新等级不高于旧等级，跳过
            return;
        }

        $currency = $vipUpgradeConfig['currency'] ?? config('app.currency', 'USD');

        // 遍历从 oldLevel+1 到 newLevel 的所有等级
        for ($i = $oldLevelIndex + 1; $i <= $newLevelIndex; $i++) {
            $level = $allLevels[$i];
            
            // 配置中的键可能是字符串格式，需要转换为字符串来匹配
            $levelKey = (string) $level;

            // 检查该等级是否有奖励配置
            if (!isset($vipUpgradeConfig['levels'][$levelKey])) {
                // 该等级没有奖励配置，跳过
                continue;
            }

            $rewardAmount = (float) $vipUpgradeConfig['levels'][$levelKey];

            if ($rewardAmount <= 0) {
                // 奖励金额为 0，跳过
                continue;
            }

            // 检查是否已经给过该等级的奖励
            $rewardTypeKey = 'vip_upgrade_' . $level;
            $existingReward = InvitationReward::where('invitation_id', $invitation->id)
                ->where('source_type', InvitationReward::SOURCE_TYPE_VIP)
                ->where('related_id', $rewardTypeKey)
                ->first();

            if ($existingReward) {
                // 已经给过该等级的奖励，跳过
                continue;
            }

            // 创建邀请奖励记录（会根据 KYC 状态自动决定是否发放）
            $this->rewardService->createRewardWithKycCheck($invitation, [
                'user_id' => $invitation->inviter_id, // 奖励给邀请人
                'invitation_id' => $invitation->id,
                'source_type' => InvitationReward::SOURCE_TYPE_VIP,
                'reward_type' => $currency,
                'reward_amount' => $rewardAmount,
                'wager' => 0, // VIP 升级奖励没有 wager
                'related_id' => $rewardTypeKey, // 奖励类型标识（vip_upgrade_1, vip_upgrade_2 等）
            ]);
        }
    }
}
