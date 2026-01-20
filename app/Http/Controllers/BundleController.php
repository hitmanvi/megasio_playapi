<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\Bundle;
use App\Models\BundlePurchase;
use App\Models\PaymentMethod;
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
     */
    public function index(Request $request): JsonResponse
    {
        $currency = $request->query('currency', 'USD');
        $limit = (int) $request->query('limit', 20);

        $bundles = $this->bundleService->getAvailableBundlesPaginated($currency, $limit);

        return $this->responseListWithPaginator($bundles);
    }

    /**
     * 获取Bundle详情
     */
    public function show(int $id): JsonResponse
    {
        $bundle = Bundle::find($id);
        if (!$bundle || !$bundle->enabled) {
            return $this->error(ErrorCode::NOT_FOUND, 'Bundle not found');
        }

        return $this->responseItem($bundle->toApiArray());
    }

    /**
     * 创建购买订单
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'bundle_id' => 'required|integer|exists:bundles,id',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'deposit_info' => 'nullable|array',
            'extra_info' => 'nullable|array',
            'native_app' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED);
        }

        try {
            $result = $this->bundleService->createPurchase(
                $user->id,
                $request->input('bundle_id'),
                $request->input('payment_method_id'),
                $request->ip(),
                $request->input('deposit_info', []),
                $request->input('extra_info', []),
                $request->input('native_app', '')
            );

            return $this->responseItem($result);
        } catch (Exception $e) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $e->getMessage());
        }
    }

    /**
     * 获取用户的购买记录
     */
    public function purchases(Request $request): JsonResponse
    {
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
     */
    public function purchaseDetail(Request $request, string $orderNo): JsonResponse
    {
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

    /**
     * 获取支付表单字段
     */
    public function formFields(Request $request): JsonResponse
    {
        $request->validate([
            'bundle_id' => 'required|integer|exists:bundles,id',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
        ]);

        $bundle = Bundle::find($request->input('bundle_id'));
        if (!$bundle || !$bundle->enabled) {
            return $this->error(ErrorCode::NOT_FOUND, 'Bundle not found');
        }

        $paymentMethod = PaymentMethod::find($request->input('payment_method_id'));
        if (!$paymentMethod || !$paymentMethod->enabled) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        try {
            $fields = $this->bundleService->getFormFields((float) $bundle->getCurrentPrice(), $paymentMethod);
            return $this->responseItem($fields);
        } catch (Exception $e) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $e->getMessage());
        }
    }

    /**
     * 获取Bundle购买订单状态集合
     */
    public function purchaseStatuses(): JsonResponse
    {
        $statuses = [
            BundlePurchase::STATUS_PENDING,
            BundlePurchase::STATUS_COMPLETED,
            BundlePurchase::STATUS_FAILED,
            BundlePurchase::STATUS_CANCELLED,
            BundlePurchase::STATUS_REFUNDED,
        ];

        return $this->responseItem($statuses);
    }
}
