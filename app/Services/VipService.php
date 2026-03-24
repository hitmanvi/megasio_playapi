<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserVip;
use App\Models\VipLevel;
use App\Models\VipLevelGroup;

class VipService
{
    /**
     * 默认 VIP 等级
     */
    const DEFAULT_LEVEL = VipLevel::DEFAULT_LEVEL;

    /**
     * 获取用户VIP信息，不存在则创建
     */
    public function getOrCreateVip(User $user): UserVip
    {
        return $user->vip ?? $user->vip()->create([
            'level' => self::DEFAULT_LEVEL,
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
            'group' => $currentLevelInfo['group'] ?? null,
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
    public function addExp(User $user, float $exp): UserVip
    {
        $vip = $this->getOrCreateVip($user);
        $vip->addExp($exp);
        return $vip;
    }

    /**
     * 获取所有启用的等级配置（按 level 自然增长排序：1, 2, 3, 4...）
     */
    public function getAllLevels(): array
    {
        return VipLevel::allEnabledApiArrays();
    }

    /**
     * 获取指定等级信息
     */
    public function getLevelInfo(int $level): ?array
    {
        return VipLevel::infoForLevel($level);
    }

    /**
     * 获取等级配置（简化版，返回数组）
     */
    public function getLevel(int $level): ?array
    {
        return VipLevel::infoForLevel($level);
    }

    /**
     * 获取所有等级标识（level 数组）
     */
    public function getLevelKeys(): array
    {
        return VipLevel::levelKeys();
    }

    /**
     * 获取等级所需经验值
     */
    public function getRequiredExp(int $level): int
    {
        return VipLevel::requiredExpFor($level);
    }

    /**
     * 根据经验值计算等级
     * 等级是数字自然增长模式（1, 2, 3, 4...），按 required_exp 从高到低查找
     */
    public function calculateLevelFromExp(float $exp): int
    {
        return VipLevel::calculateLevelFromExp($exp);
    }

    /**
     * 获取下一等级信息（当前 level + 1）
     */
    public function getNextLevel(int $currentLevel): ?array
    {
        $nextLevel = $currentLevel + 1;
        return $this->getLevel($nextLevel);
    }

    /**
     * 获取默认等级
     */
    public function getDefaultLevel(): int
    {
        return self::DEFAULT_LEVEL;
    }

    /**
     * 获取所有VIP等级组列表（分页）
     *
     * @param int $perPage 每页数量
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllGroupsPaginated(int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return VipLevelGroup::enabled()
            ->ordered()
            ->paginate($perPage);
    }
}
