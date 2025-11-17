<?php

namespace App\Services;

use App\Models\Invitation;
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
        $totalInvited = Invitation::where('inviter_id', $userId)->count();
        
        // 统计被邀请用户的状态
        $activeInvited = Invitation::where('inviter_id', $userId)
            ->whereHas('invitee', function ($query) {
                $query->where('status', 'active');
            })
            ->count();

        return [
            'total_invited' => $totalInvited,
            'active_invited' => $activeInvited,
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
            ->with('invitee')
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

        return [
            'id' => $invitation->id,
            'invitee' => [
                'uid' => $invitee->uid,
                'name' => $invitee->name,
                'phone' => $invitee->phone,
                'email' => $invitee->email,
                'status' => $invitee->status,
            ],
            'invited_at' => $invitation->created_at->toIso8601String(),
        ];
    }
}

