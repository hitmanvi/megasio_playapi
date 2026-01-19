<?php

namespace App\Http\Controllers;

use App\Services\BonusTaskService;
use App\Enums\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BonusTaskController extends Controller
{
    protected BonusTaskService $bonusTaskService;

    public function __construct(BonusTaskService $bonusTaskService)
    {
        $this->bonusTaskService = $bonusTaskService;
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
}
