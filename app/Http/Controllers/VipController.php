<?php

namespace App\Http\Controllers;

use App\Services\VipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class VipController extends Controller
{
    protected VipService $vipService;

    public function __construct(VipService $vipService)
    {
        $this->vipService = $vipService;
    }

    /**
     * 获取所有 VIP 等级列表
     */
    public function levels(): JsonResponse
    {
        $levels = $this->vipService->getAllLevels();
        return $this->responseList($levels);
    }

    /**
     * 获取所有 VIP 等级组列表（分页）
     */
    public function groups(Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->input('per_page', 20));
        $groupsPaginator = $this->vipService->getAllGroupsPaginated($perPage);

        // 格式化返回数据
        $formattedGroups = $groupsPaginator->items();
        $formattedGroups = collect($formattedGroups)->map(function ($group) {
            return $group->toApiArray();
        });

        // 创建新的分页器，使用格式化后的数据
        $formattedPaginator = new LengthAwarePaginator(
            $formattedGroups,
            $groupsPaginator->total(),
            $groupsPaginator->perPage(),
            $groupsPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }
}
