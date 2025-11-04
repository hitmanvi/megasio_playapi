<?php

namespace App\Http\Controllers;

use App\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Enums\ErrorCode;

class DepositController extends Controller
{
    protected DepositService $depositService;

    public function __construct(DepositService $depositService)
    {
        $this->depositService = $depositService;
    }
    /**
     * 获取存款订单列表
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
        $deposits = $this->depositService->getUserDeposits($user->id, $filters, $perPage);

        // 格式化返回数据
        $deposits->getCollection()->transform(function ($deposit) {
            return $this->depositService->formatDepositForResponse($deposit, false);
        });

        return $this->responseListWithPaginator($deposits);
    }

    /**
     * 创建存款订单
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 验证请求参数
        $validator = Validator::make($request->all(), [
            'payment_method_id'  => 'required',
            'amount'      => 'required',
            'native_app'  => 'required',
            'currency'    => 'required',
            'ua'          => '',
            'channel_id'  => '',
            'extra_info'  => '',
            'aaid'        => '',
            'android_id'  => '',
            'client_id'   => '',
            'fb_info'     => '',
        ]);

        if ($validator->fails()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validator->errors());
        }

        // 获取支付方式
        $paymentMethod = $this->depositService->getPaymentMethod($request->input('payment_method_id'));
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        // 验证支付方式
        $amount = (float)$request->input('amount');
        $currency = $request->input('currency');
        $validation = $this->depositService->validatePaymentMethod($paymentMethod, $currency, $amount);
        
        if (!$validation['valid']) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validation['errors']);
        }

        // 创建存款订单
        $jumpData = $this->depositService->createDeposit(
            $user->id,
            $paymentMethod,
            $currency,
            $amount,
            $request->input('deposit_info', []),
            $request->input('extra_info', []),
            $request->ip()
        );

        // 返回创建的存款订单信息
        return $this->responseItem($jumpData);
    }

    /**
     * 获取存款订单信息
     * 
     * 通过 order_no 查询
     */
    public function show(Request $request, string $orderNo): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        $deposit = $this->depositService->getDepositByOrderNo($user->id, $orderNo);
        if (!$deposit) {
            return $this->error(ErrorCode::NOT_FOUND, 'Deposit not found');
        }

        return $this->responseItem($this->depositService->formatDepositForResponse($deposit, true));
    }

    /**
     * 获取充值表单字段配置
     * 
     * 根据支付方式返回用户需要填写的表单字段
     */
    public function formFields(Request $request)
    {
        $amount = (float)$request->input('amount');
        $paymentMethod = $this->depositService->getPaymentMethod($request->input('payment_method_id'));
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        $fields = $this->depositService->getFormFields($amount, $paymentMethod);

        return $this->responseItem($fields);
    }
}

