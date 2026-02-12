<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\Kyc;
use App\Models\User;
use App\Services\InvitationRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    protected InvitationRewardService $rewardService;

    public function __construct()
    {
        $this->rewardService = new InvitationRewardService();
    }

    /**
     * KYC 完成通知接口
     * 当后台管理系统完成 KYC 审核后，调用此接口通知 API
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function notifyKycCompleted(Request $request): JsonResponse
    {
        // 验证请求参数
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'status' => 'required|string|in:' . implode(',', [
                Kyc::STATUS_APPROVED,
                Kyc::STATUS_ADVANCED_PENDING,
                Kyc::STATUS_ADVANCED_APPROVED,
                Kyc::STATUS_ADVANCED_REJECTED,
                Kyc::STATUS_ENHANCED_PENDING,
                Kyc::STATUS_ENHANCED_APPROVED,
                Kyc::STATUS_ENHANCED_REJECTED,
            ]),
        ], [
            'user_id.required' => 'User ID is required',
            'user_id.integer' => 'User ID must be an integer',
            'user_id.exists' => 'User not found',
            'status.required' => 'KYC status is required',
            'status.in' => 'Invalid KYC status',
        ]);

        try {
            $userId = (int) $request->input('user_id');
            $status = $request->input('status');

            // 查找用户
            $user = User::find($userId);
            if (!$user) {
                return $this->error(ErrorCode::USER_NOT_FOUND);
            }

            // 检查 KYC 状态是否为已认证状态
            $isVerified = in_array($status, [
                Kyc::STATUS_APPROVED,
                Kyc::STATUS_ADVANCED_PENDING,
                Kyc::STATUS_ADVANCED_APPROVED,
                Kyc::STATUS_ADVANCED_REJECTED,
                Kyc::STATUS_ENHANCED_PENDING,
                Kyc::STATUS_ENHANCED_APPROVED,
                Kyc::STATUS_ENHANCED_REJECTED,
            ]);

            // 如果 KYC 已认证，查找该用户相关的激活邀请关系并发放奖励
            if ($isVerified) {
                // 查找该用户作为邀请人和被邀请人的激活邀请关系，检查并发放相关奖励
                $paidCount = $this->rewardService->payPendingRewardsForKycCompletedUser($userId);
                
                Log::info('KYC completed notification processed', [
                    'user_id' => $userId,
                    'status' => $status,
                    'rewards_paid' => $paidCount,
                ]);

                return $this->responseItem([
                    'user_id' => $userId,
                    'status' => $status,
                    'rewards_paid' => $paidCount,
                    'message' => 'KYC completion notification processed successfully',
                ]);
            }

            // KYC 状态不是已认证状态，只记录日志
            Log::info('KYC status updated (not verified)', [
                'user_id' => $userId,
                'status' => $status,
            ]);

            return $this->responseItem([
                'user_id' => $userId,
                'status' => $status,
                'rewards_paid' => 0,
                'message' => 'KYC status updated (not verified, no rewards processed)',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process KYC completion notification', [
                'user_id' => $request->input('user_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(ErrorCode::INTERNAL_ERROR, [
                'message' => 'Failed to process KYC completion notification',
            ]);
        }
    }
}
