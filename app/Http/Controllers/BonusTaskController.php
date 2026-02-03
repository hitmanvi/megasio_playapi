<?php

namespace App\Http\Controllers;

use App\Models\BonusTask;
use App\Services\BonusTaskService;
use App\Services\PromotionService;
use App\Enums\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BonusTaskController extends Controller
{
    protected BonusTaskService $bonusTaskService;
    protected PromotionService $promotionService;

    public function __construct()
    {
        $this->bonusTaskService = new BonusTaskService();
        $this->promotionService = new PromotionService();
    }

    /**
     * 获取 BonusTask 列表
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 获取 status 过滤参数（可选）
        $status = $request->input('status');

        // 验证 status 参数（如果提供）
        if ($status !== null) {
            $validStatuses = [
                BonusTask::STATUS_PENDING,
                BonusTask::STATUS_ACTIVE,
                BonusTask::STATUS_COMPLETED,
                BonusTask::STATUS_CLAIMED,
                BonusTask::STATUS_EXPIRED,
                BonusTask::STATUS_CANCELLED,
                BonusTask::STATUS_DEPLETED,
            ];
            
            if (!in_array($status, $validStatuses)) {
                return $this->error(ErrorCode::VALIDATION_ERROR, 'Invalid status value');
            }
        }

        $tasks = $this->bonusTaskService->getTasks($user->id, $status);

        $tasks = $tasks->map(function ($task) {
            return $this->bonusTaskService->formatBonusTask($task);
        });

        return $this->responseList($tasks->toArray());
    }

    /**
     * 获取可领取的 BonusTask 列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function claimable(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        $tasks = $this->bonusTaskService->getClaimableTasks($user->id);

        $tasks = $tasks->map(function ($task) {
            return $this->bonusTaskService->formatBonusTask($task);
        });

        return $this->responseItem($tasks);
    }

    /**
     * 领取 BonusTask 奖励
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function claim(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        try {
            $result = $this->bonusTaskService->claim($user->id, $id);

            $data = $this->bonusTaskService->formatBonusTask($result['task']);
            $data['claim_amount'] = $result['claim_amount'];
            $data['currency'] = $result['currency'];

            return $this->responseItem($data);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Bonus task not found') {
                return $this->error(ErrorCode::NOT_FOUND, $e->getMessage());
            }
            if ($e->getMessage() === 'Bonus task is not claimable') {
                return $this->error(ErrorCode::OPERATION_NOT_ALLOWED, $e->getMessage());
            }
            return $this->error(ErrorCode::INTERNAL_ERROR, $e->getMessage());
        }
    }

    /**
     * 获取用户充值奖励状态
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function depositBonusStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        $status = $this->promotionService->getDepositBonusStatus($user->id);

        return $this->responseItem($status);
    }

    /**
     * 获取充值奖励配置
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function depositBonusConfig(Request $request): JsonResponse
    {
        $config = $this->promotionService->getDepositBonusConfig();

        return $this->responseItem($config);
    }
}
