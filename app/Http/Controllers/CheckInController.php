<?php

namespace App\Http\Controllers;

use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    protected CheckInService $checkInService;

    public function __construct(CheckInService $checkInService)
    {
        $this->checkInService = $checkInService;
    }

    /**
     * 用户签到
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $isBonusCheckIn = $request->input('bonus', false);
        $checkIn = $this->checkInService->checkIn($userId, $isBonusCheckIn);

        return $this->responseItem($this->checkInService->formatCheckIn($checkIn));
    }

    /**
     * 获取签到状态
     */
    public function status(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $status = $this->checkInService->getStatus($userId);

        return $this->responseItem($status);
    }

    /**
     * 获取签到历史
     */
    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = $request->input('per_page', 20);

        $history = $this->checkInService->getHistory($userId, $perPage);

        return $this->responseListWithPaginator($history);
    }

    /**
     * 获取签到配置
     */
    public function config(Request $request): JsonResponse
    {
        $config = $this->checkInService->getCheckInConfig();

        return $this->responseItem($config);
    }
}

