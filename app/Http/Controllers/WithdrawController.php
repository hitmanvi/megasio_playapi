<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\Kyc;
use App\Models\Withdraw;
use App\Services\WithdrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WithdrawController extends Controller
{
    protected WithdrawService $withdrawService;

    public function __construct(WithdrawService $withdrawService)
    {
        $this->withdrawService = $withdrawService;
    }

    /**
     * 获取提款订单列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 构建筛选条件
        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }
        if ($request->has('pay_status')) {
            $filters['pay_status'] = $request->input('pay_status');
        }
        if ($request->has('currency')) {
            $filters['currency'] = $request->input('currency');
        }
        if ($request->has('payment_method_id')) {
            $filters['payment_method_id'] = $request->input('payment_method_id');
        }

        $perPage = max(1, (int)$request->input('per_page', 20));
        $withdraws = $this->withdrawService->getUserWithdraws($user->id, $filters, $perPage);

        // 格式化返回数据
        $withdraws->getCollection()->transform(function ($withdraw) {
            return $this->withdrawService->formatWithdrawForResponse($withdraw, false);
        });

        return $this->responseListWithPaginator($withdraws);
    }

    /**
     * 创建提款订单
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 检查 KYC 认证状态
        $kyc = Kyc::where('user_id', $user->id)->first();
        if (!$kyc || !$kyc->isVerified()) {
            return $this->error(ErrorCode::FORBIDDEN, 'KYC verification required for withdrawal');
        }

        // 验证请求参数
        $validator = Validator::make($request->all(), [
            'payment_method_id'  => 'required',
            'amount'      => 'required',
            'currency'    => 'required',
            'withdraw_info' => '',
            'extra_info'  => '',
        ]);

        if ($validator->fails()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validator->errors());
        }

        // 获取支付方式
        $paymentMethod = $this->withdrawService->getPaymentMethod($request->input('payment_method_id'));
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        // 验证支付方式
        $amount = (float)$request->input('amount');
        $currency = $request->input('currency');
        $validation = $this->withdrawService->validatePaymentMethod($paymentMethod, $currency, $amount);
        
        if (!$validation['valid']) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validation['errors']);
        }

        // 创建提款订单
        $withdraw = $this->withdrawService->createWithdraw(
            $user->id,
            $paymentMethod,
            $currency,
            $amount,
            $request->input('withdraw_info', []),
            $request->input('extra_info', []),
            $request->ip()
        );

        // 返回创建的提款订单信息
        return $this->responseItem($this->withdrawService->formatWithdrawForResponse($withdraw, true));
    }

    /**
     * 获取提款订单信息
     * 
     * 通过 order_no 查询
     */
    public function show(Request $request, string $orderNo): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        $withdraw = $this->withdrawService->getWithdrawByOrderNo($user->id, $orderNo);
        if (!$withdraw) {
            return $this->error(ErrorCode::NOT_FOUND, 'Withdraw not found');
        }

        return $this->responseItem($this->withdrawService->formatWithdrawForResponse($withdraw, true));
    }

    /**
     * 获取提款表单字段配置
     * 
     * 根据支付方式返回用户需要填写的表单字段
     */
    public function formFields(Request $request)
    {
        $amount = (float)$request->input('amount');
        $paymentMethod = $this->withdrawService->getPaymentMethod($request->input('payment_method_id'));
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        $fields = $this->withdrawService->getFormFields($amount, $paymentMethod);

        return $this->responseItem($fields);
    }

    /**
     * 获取提款订单状态集合
     */
    public function statuses(): JsonResponse
    {
        $statuses = [
            Withdraw::STATUS_PENDING,
            Withdraw::STATUS_PROCESSING,
            Withdraw::STATUS_COMPLETED,
            Withdraw::STATUS_FAILED,
            Withdraw::STATUS_CANCELLED,
            Withdraw::STATUS_REJECTED,
        ];

        return $this->responseItem($statuses);
    }
}

