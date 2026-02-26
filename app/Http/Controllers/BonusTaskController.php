<?php

namespace App\Http\Controllers;

use App\Models\BonusTask;
use App\Services\BonusTaskService;
use App\Services\WeeklyCashbackService;
use App\Services\PromotionService;
use App\Enums\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

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

        // 获取 status 过滤参数（可选，支持单个值或数组）
        $status = $request->input('status');
        
        // 确保是数组格式（如果提供）
        if ($status !== null && !is_array($status)) {
            $status = [$status];
        }

        // 验证 status 参数（如果提供）
        if ($status !== null && is_array($status)) {
            $validStatuses = [
                BonusTask::STATUS_PENDING,
                BonusTask::STATUS_ACTIVE,
                BonusTask::STATUS_COMPLETED,
                BonusTask::STATUS_CLAIMED,
                BonusTask::STATUS_EXPIRED,
                BonusTask::STATUS_CANCELLED,
                BonusTask::STATUS_DEPLETED,
            ];
            
            // 验证数组中的每个状态值
            foreach ($status as $s) {
                if (!in_array($s, $validStatuses)) {
                    return $this->error(ErrorCode::VALIDATION_ERROR, 'Invalid status value: ' . $s);
                }
            }
        }

        // 获取列表前先检查并更新该用户已过期的 task 状态
        $this->bonusTaskService->expireOverdueTasksForUser($user->id);

        $perPage = max(1, (int) $request->input('per_page', 20));
        $tasksPaginator = $this->bonusTaskService->getTasksPaginated($user->id, $status, $perPage);

        // 格式化返回数据
        $formattedTasks = $tasksPaginator->items();
        $formattedTasks = collect($formattedTasks)->map(function ($task) {
            return $this->bonusTaskService->formatBonusTask($task);
        });

        // 创建新的分页器，使用格式化后的数据
        $formattedPaginator = new LengthAwarePaginator(
            $formattedTasks,
            $tasksPaginator->total(),
            $tasksPaginator->perPage(),
            $tasksPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
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

    /**
     * 获取 BonusTask 详情
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        $task = BonusTask::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error(ErrorCode::NOT_FOUND, 'Bonus task not found');
        }

        return $this->responseItem($this->bonusTaskService->formatBonusTask($task));
    }

    /**
     * 获取 BonusTask 统计数据
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        $stats = $this->bonusTaskService->getStats($user->id);

        $weeklyCashbackTotal = (new WeeklyCashbackService())->getClaimedTotalForUser($user->id);
        $data = $stats;
        $data['total'] = (float) $data['total'] + $weeklyCashbackTotal;
        $data['currency'] = config('app.currency', 'USD');

        return $this->responseItem($data);
    }

    /**
     * 激活指定的 BonusTask
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function active(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        try {
            $task = BonusTask::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$task) {
                return $this->error(ErrorCode::NOT_FOUND, 'Bonus task not found');
            }

            // 检查任务状态
            if (!$task->isPending()) {
                return $this->error(ErrorCode::OPERATION_NOT_ALLOWED, 'Only pending tasks can be activated');
            }

            // 检查任务是否过期
            if ($task->isExpired()) {
                return $this->error(ErrorCode::OPERATION_NOT_ALLOWED, 'Task has expired');
            }

            // 检查是否已有激活的任务，如果有则将其变为 pending
            $activeTask = $this->bonusTaskService->getActiveBonusTask($user->id);
            if ($activeTask && $activeTask->id !== $task->id) {
                $activeTask->status = BonusTask::STATUS_PENDING;
                $activeTask->save();
            }

            // 激活任务
            $this->bonusTaskService->activate($task);
            $task->refresh();

            return $this->responseItem($this->bonusTaskService->formatBonusTask($task));
        } catch (\Exception $e) {
            return $this->error(ErrorCode::INTERNAL_ERROR, $e->getMessage());
        }
    }
}
