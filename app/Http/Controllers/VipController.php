<?php

namespace App\Http\Controllers;

use App\Services\VipService;
use Illuminate\Http\JsonResponse;

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
}
