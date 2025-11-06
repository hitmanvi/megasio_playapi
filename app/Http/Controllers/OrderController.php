<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\ErrorCode;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * 获取订单列表
     * 
     * 支持的时间范围参数：24h, 7d, 30d
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 验证时间范围参数
        $period = $request->input('period');
        $allowedPeriods = ['24h', '7d', '30d'];
        if ($period && !in_array($period, $allowedPeriods)) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'Period must be one of: 24h, 7d, 30d');
        }

        // 构建筛选条件
        $filters = [];
        if ($request->has('currency')) {
            $filters['currency'] = $request->input('currency');
        }
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }
        if ($request->has('game_id')) {
            $filters['game_id'] = $request->input('game_id');
        }
        if ($period) {
            $filters['period'] = $period;
        }

        $perPage = max(1, (int)$request->input('per_page', 20));
        $orders = $this->orderService->getUserOrdersPaginated($user->id, $filters, $perPage);

        // 格式化返回数据
        $orders->getCollection()->transform(function ($order) {
            return $this->orderService->formatOrderForResponse($order, false);
        });

        return $this->responseListWithPaginator($orders);
    }
}

