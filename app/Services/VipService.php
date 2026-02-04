<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserVip;
use App\Models\VipLevel;

class VipService
{
    /**
     * 获取用户VIP信息，不存在则创建
     */
    public function getOrCreateVip(User $user): UserVip
    {
        return $user->vip ?? $user->vip()->create([
            'level' => VipLevel::getDefaultLevel(),
            'exp' => 0,
        ]);
    }

    /**
     * 格式化VIP信息用于API响应
     */
    public function formatVipForResponse(UserVip $vip): array
    {
        $currentLevelInfo = $vip->getCurrentLevelInfo();
        
        return [
            'level' => $vip->level,
            'level_name' => $currentLevelInfo['name'] ?? null,
            'level_icon' => $currentLevelInfo['group_icon'] ?? null,
            'group' => $currentLevelInfo['group_name'] ?? null,
            'exp' => $vip->exp,
            'benefits' => $vip->getBenefits(),
            'next_level' => $vip->getNextLevelInfo(),
        ];
    }

    /**
     * 获取用户VIP信息并格式化
     */
    public function getUserVipInfo(User $user): array
    {
        $vip = $this->getOrCreateVip($user);
        return $this->formatVipForResponse($vip);
    }

    /**
     * 增加用户经验值
     */
    public function addExp(User $user, int $exp): UserVip
    {
        $vip = $this->getOrCreateVip($user);
        $vip->addExp($exp);
        return $vip;
    }

    /**
     * 获取所有VIP等级列表
     */
    public function getAllLevels(): array
    {
        return VipLevel::enabled()
            ->with('group')
            ->ordered()
            ->get()
            ->map(fn($level) => $level->toApiArray())
            ->toArray();
    }

    /**
     * 获取指定等级信息
     */
    public function getLevelInfo(string $level): ?array
    {
        $levelConfig = VipLevel::getLevelCached($level);
        
        if (!$levelConfig) {
            return null;
        }

        return [
            'level' => $levelConfig['level'],
            'name' => $levelConfig['name'],
            'icon' => $levelConfig['group_icon'] ?? null,
            'group' => $levelConfig['group_name'] ?? null,
            'required_exp' => $levelConfig['required_exp'],
            'description' => $levelConfig['description'],
            'benefits' => $levelConfig['benefits'],
        ];
    }
}
