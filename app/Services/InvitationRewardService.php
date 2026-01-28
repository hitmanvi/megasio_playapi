<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Str;

class InvitationRewardService
{
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
}
