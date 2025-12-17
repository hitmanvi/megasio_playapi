<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\Bundle;
use App\Services\BundleService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    protected BundleService $bundleService;

    public function __construct()
    {
        $this->bundleService = new BundleService();
    }

    /**
     * 获取可用的Bundle列表
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 检查是否为Bundle模式
        if (!BundleService::isBundleMode()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'Bundle mode is not enabled');
        }

        $currency = $request->query('currency', 'USD');
        $limit = (int) $request->query('limit', 20);

        $bundles = $this->bundleService->getAvailableBundlesPaginated($currency, $limit);

        return $this->responseListWithPaginator($bundles);
    }

    /**
     * 获取Bundle详情
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        if (!BundleService::isBundleMode()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'Bundle mode is not enabled');
        }

        $bundle = Bundle::findCached($id);
        if (!$bundle || !$bundle->enabled) {
            return $this->error(ErrorCode::NOT_FOUND, 'Bundle not found');
        }

        return $this->responseItem($bundle->toApiArray());
    }

    /**
     * 创建购买订单
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function purchase(Request $request): JsonResponse
    {
        if (!BundleService::isBundleMode()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'Bundle mode is not enabled');
        }

        $request->validate([
            'bundle_id' => 'required|integer|exists:bundles,id',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED);
        }

        try {
            $purchase = $this->bundleService->createPurchase(
                $user->id,
                $request->input('bundle_id'),
                $request->input('payment_method_id'),
                $request->ip()
            );

            return $this->responseItem($purchase->toApiArray());
        } catch (Exception $e) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $e->getMessage());
        }
    }

    /**
     * 获取用户的购买记录
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function purchases(Request $request): JsonResponse
    {
        if (!BundleService::isBundleMode()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'Bundle mode is not enabled');
        }

        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED);
        }

        $limit = (int) $request->query('limit', 20);

        $purchases = $this->bundleService->getUserPurchasesPaginated($user->id, $limit);

        return $this->responseListWithPaginator($purchases);
    }

    /**
     * 获取购买详情
     *
     * @param Request $request
     * @param string $orderNo
     * @return JsonResponse
     */
    public function purchaseDetail(Request $request, string $orderNo): JsonResponse
    {
        if (!BundleService::isBundleMode()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'Bundle mode is not enabled');
        }

        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED);
        }

        $purchase = $this->bundleService->getPurchaseByOrderNo($orderNo);
        if (!$purchase || $purchase->user_id !== $user->id) {
            return $this->error(ErrorCode::NOT_FOUND, 'Purchase not found');
        }

        return $this->responseItem($purchase->toApiArray());
    }

}

