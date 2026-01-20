<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\Kyc;
use App\Models\Redeem;
use App\Services\RedeemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RedeemController extends Controller
{
    protected RedeemService $redeemService;

    public function __construct()
    {
        $this->redeemService = new RedeemService();
    }

    /**
     * 获取兑换记录列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED);
        }

        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }
        if ($request->has('pay_status')) {
            $filters['pay_status'] = $request->input('pay_status');
        }

        $perPage = max(1, (int) $request->input('per_page', 20));
        $redeems = $this->redeemService->getUserRedeems($user->id, $filters, $perPage);

        $redeems->getCollection()->transform(function ($redeem) {
            return $this->redeemService->formatRedeemForResponse($redeem, false);
        });

        return $this->responseListWithPaginator($redeems);
    }

    /**
     * 创建兑换订单 (SC -> USD)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED);
        }

        // 检查 KYC 认证状态
        $kyc = Kyc::where('user_id', $user->id)->first();
        if (!$kyc || !$kyc->isVerified()) {
            return $this->error(ErrorCode::FORBIDDEN, 'KYC verification required for redemption');
        }

        $validator = Validator::make($request->all(), [
            'sc_amount' => 'required|numeric|min:0.00000001',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'withdraw_info' => 'nullable|array',
            'extra_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validator->errors());
        }

        // 获取支付方式
        $paymentMethod = $this->redeemService->getPaymentMethod($request->input('payment_method_id'));
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        // 计算 USD 金额
        $scAmount = (float) $request->input('sc_amount');
        $calculation = $this->redeemService->calculateUsdAmount($scAmount);

        // 验证支付方式
        $validation = $this->redeemService->validatePaymentMethod($paymentMethod, $calculation['usd_amount']);
        if (!$validation['valid']) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validation['errors']);
        }

        try {
            $redeem = $this->redeemService->createRedeem(
                $user->id,
                $scAmount,
                $paymentMethod,
                $request->input('withdraw_info', []),
                $request->input('extra_info', []),
                $request->ip()
            );

            return $this->responseItem($this->redeemService->formatRedeemForResponse($redeem, true));
        } catch (\Exception $e) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $e->getMessage());
        }
    }

    /**
     * 获取兑换订单详情
     */
    public function show(Request $request, string $orderNo): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED);
        }

        $redeem = $this->redeemService->getRedeemByOrderNo($user->id, $orderNo);
        if (!$redeem) {
            return $this->error(ErrorCode::NOT_FOUND, 'Redeem not found');
        }

        return $this->responseItem($this->redeemService->formatRedeemForResponse($redeem, true));
    }

    /**
     * 获取表单字段配置
     */
    public function formFields(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sc_amount' => 'required|numeric|min:0.00000001',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
        ]);

        if ($validator->fails()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validator->errors());
        }

        $paymentMethod = $this->redeemService->getPaymentMethod($request->input('payment_method_id'));
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        // 计算 USD 金额
        $scAmount = (float) $request->input('sc_amount');
        $calculation = $this->redeemService->calculateUsdAmount($scAmount);

        try {
            $fields = $this->redeemService->getFormFields($calculation['usd_amount'], $paymentMethod);
            return $this->responseItem($fields);
        } catch (\Exception $e) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $e->getMessage());
        }
    }

    /**
     * 获取当前兑换汇率
     */
    public function exchangeRate(): JsonResponse
    {
        $rate = $this->redeemService->getExchangeRate();
        
        return $this->responseItem([
            'from' => 'SC',
            'to' => 'USD',
            'rate' => $rate,
        ]);
    }

    /**
     * 获取兑换订单状态集合
     */
    public function statuses(): JsonResponse
    {
        $statuses = [
            Redeem::STATUS_PENDING,
            Redeem::STATUS_PROCESSING,
            Redeem::STATUS_COMPLETED,
            Redeem::STATUS_FAILED,
            Redeem::STATUS_CANCELLED,
            Redeem::STATUS_REJECTED,
        ];

        return $this->responseItem($statuses);
    }
}

