<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserVip;

class VipService
{
    /**
     * 获取用户VIP信息，不存在则创建
     */
    public function getOrCreateVip(User $user): UserVip
    {
        return $user->vip ?? $user->vip()->create([
            'level' => UserVip::LEVEL_BRONZE,
            'exp' => 0,
        ]);
    }

    /**
     * 格式化VIP信息用于API响应
     */
    public function formatVipForResponse(UserVip $vip): array
    {
        return [
            'level' => $vip->level,
            'exp' => $vip->exp,
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
}

