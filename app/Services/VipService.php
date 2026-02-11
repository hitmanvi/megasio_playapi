<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserVip;
use App\Models\VipLevel;
use App\Models\VipLevelGroup;

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
    public function getLevelInfo(int $level): ?array
    {
        $levelConfig = VipLevel::getLevelCached($level);
        
        if (!$levelConfig) {
            return null;
        }

        return [
            'level' => $levelConfig['level'],
            'required_exp' => $levelConfig['required_exp'],
            'description' => $levelConfig['description'],
            'benefits' => $levelConfig['benefits'],
            'group' => $levelConfig['group'],
        ];
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
