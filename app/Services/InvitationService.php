<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\InvitationReward;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class InvitationService
{
    /**
     * 获取邀请统计数据
     *
     * @param int $userId
     * @return array
     */
    public function getInvitationStats(int $userId): array
    {
        $baseQuery = Invitation::where('inviter_id', $userId);
        
        $totalInvited = $baseQuery->count();
        
        // 统计被邀请用户的状态
        $activeInvited = (clone $baseQuery)
            ->whereHas('invitee', function ($query) {
                $query->where('status', 'active');
            })
            ->count();

        // 统计获得的总奖励（直接从 invitation 表的 total_reward 字段获取）
        $totalReward = (clone $baseQuery)->sum('total_reward');

        return [
            'total_invited' => $totalInvited,
            'active_invited' => $activeInvited,
            'total_reward' => (float) $totalReward,
        ];
    }

    /**
     * 获取邀请列表（分页）
     *
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getInvitationListPaginated(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Invitation::where('inviter_id', $userId)
            ->with(['invitee.vip'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * 格式化邀请列表项
     *
     * @param Invitation $invitation
     * @return array
     */
    public function formatInvitationItem(Invitation $invitation): array
    {
        $invitee = $invitation->invitee;

        // 格式化被邀请人信息（包含VIP等级和脱敏）
        $inviteeInfo = $this->formatMaskedUserInfo($invitee);

        return [
            'id' => $invitation->id,
            'invitee' => $inviteeInfo,
            'total_reward' => (float) $invitation->total_reward,
            'currency' => 'USD', // 货币代码
            'invited_at' => $invitation->created_at->toIso8601String(),
        ];
    }

    /**
     * 获取邀请关系的奖励统计
     *
     * @param int $userId 邀请人ID
     * @param int $invitationId 邀请关系ID
     * @return array
     */
    public function getInvitationRewardStats(int $userId, int $invitationId): array
    {
        // 验证邀请关系是否存在且属于该用户
        $invitation = Invitation::where('id', $invitationId)
            ->where('inviter_id', $userId)
            ->with(['invitee.vip'])
            ->first();

        if (!$invitation) {
            throw new \Exception('Invitation not found');
        }

        // 按来源类型聚合奖励总额和 wager
        $rewardStats = InvitationReward::where('invitation_id', $invitationId)
            ->selectRaw('source_type, COUNT(*) as count, SUM(reward_amount) as total_amount, SUM(wager) as total_wager')
            ->groupBy('source_type')
            ->get()
            ->map(function ($stat) {
                return [
                    'source_type' => $stat->source_type,
                    'count' => (int) $stat->count,
                    'total_amount' => (float) $stat->total_amount,
                    'total_wager' => (float) $stat->total_wager,
                ];
            })
            ->values();

        // 格式化被邀请人信息（脱敏）
        $invitee = $this->formatMaskedUserInfo($invitation->invitee);

        return [
            'invitation_id' => $invitation->id,
            'invitee' => $invitee,
            'total_reward' => (float) $invitation->total_reward,
            'currency' => 'USD', // 货币代码
            'reward_stats' => $rewardStats,
            'invited_at' => $invitation->created_at->toIso8601String(),
        ];
    }

    /**
     * 格式化脱敏后的用户信息
     *
     * @param User $user
     * @return array
     */
    protected function formatMaskedUserInfo(User $user): array
    {
        $vipLevel = null;
        $vipLevelName = null;
        
        // 获取VIP等级信息
        if ($user->relationLoaded('vip') && $user->vip) {
            $vipLevel = $user->vip->level;
            $levelInfo = $user->vip->getCurrentLevelInfo();
            $vipLevelName = $levelInfo['name'] ?? null;
        } elseif ($user->vip) {
            // 如果关系未加载，尝试加载
            $user->load('vip');
            if ($user->vip) {
                $vipLevel = $user->vip->level;
                $levelInfo = $user->vip->getCurrentLevelInfo();
                $vipLevelName = $levelInfo['name'] ?? null;
            }
        }

        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'phone' => $this->maskPhone($user->phone),
            'email' => $this->maskEmail($user->email),
            'status' => $user->status,
            'vip_level' => $vipLevel,
            'vip_level_name' => $vipLevelName,
        ];
    }

    /**
     * 脱敏手机号
     * 格式：138****8000（保留前3位和后4位）
     *
     * @param string|null $phone
     * @return string|null
     */
    protected function maskPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $length = strlen($phone);
        if ($length <= 7) {
            // 如果手机号长度小于等于7位，只保留前2位和后2位
            return substr($phone, 0, 2) . str_repeat('*', $length - 4) . substr($phone, -2);
        }

        // 保留前3位和后4位
        return substr($phone, 0, 3) . str_repeat('*', $length - 7) . substr($phone, -4);
    }

    /**
     * 脱敏邮箱
     * 格式：zh****@example.com（保留@前的前2位和@后的完整域名）
     *
     * @param string|null $email
     * @return string|null
     */
    protected function maskEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email; // 如果不是标准邮箱格式，直接返回
        }

        [$localPart, $domain] = $parts;
        $localLength = strlen($localPart);

        if ($localLength <= 2) {
            // 如果@前部分长度小于等于2，全部用*代替
            $maskedLocal = str_repeat('*', $localLength);
        } else {
            // 保留前2位，其余用*代替
            $maskedLocal = substr($localPart, 0, 2) . str_repeat('*', $localLength - 2);
        }

        return $maskedLocal . '@' . $domain;
    }
}

