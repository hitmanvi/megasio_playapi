<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Enums\ErrorCode;

class DepositController extends Controller
{
    /**
     * 获取存款订单列表
     */
    public function index(Request $request): JsonResponse
    {
        // 获取当前认证用户
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 构建查询
        $query = Deposit::query()
            ->where('user_id', $user->id)
            ->with(['paymentMethod'])
            ->orderBy('created_at', 'desc');

        // 筛选：状态
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // 筛选：支付状态
        if ($request->has('pay_status')) {
            $query->where('pay_status', $request->input('pay_status'));
        }

        // 筛选：货币类型
        if ($request->has('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        // 筛选：支付方式
        if ($request->has('payment_method_id')) {
            $query->where('payment_method_id', $request->input('payment_method_id'));
        }

        // 分页参数
        $perPage = max(1, (int)$request->input('per_page', 20));

        // 使用 Laravel 的 paginate 方法进行分页
        $deposits = $query->paginate($perPage);

        // 格式化返回数据，直接在 paginator 的 collection 上进行操作
        $deposits->getCollection()->transform(function ($deposit) {
            return [
                'order_no' => $deposit->order_no,
                'out_trade_no' => $deposit->out_trade_no,
                'currency' => $deposit->currency,
                'amount' => (float)$deposit->amount,
                'actual_amount' => $deposit->actual_amount ? (float)$deposit->actual_amount : null,
                'pay_fee' => $deposit->pay_fee ? (float)$deposit->pay_fee : null,
                'payment_method' => $deposit->paymentMethod ? [
                    'key' => $deposit->paymentMethod->key,
                    'name' => $deposit->paymentMethod->name,
                    'display_name' => $deposit->paymentMethod->display_name,
                    'icon' => $deposit->paymentMethod->icon,
                ] : null,
                'status' => $deposit->status,
                'pay_status' => $deposit->pay_status,
                'expired_at' => $deposit->expired_at ? $deposit->expired_at->format('Y-m-d H:i:s') : null,
                'finished_at' => $deposit->finished_at ? $deposit->finished_at->format('Y-m-d H:i:s') : null,
                'created_at' => $deposit->created_at->format('Y-m-d H:i:s'),
                'is_expired' => $deposit->isExpired(),
            ];
        });

        return $this->responseListWithPaginator($deposits);
    }

    /**
     * 创建存款订单
     */
    public function store(Request $request): JsonResponse
    {
        // 获取当前认证用户
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 验证请求参数
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
            'deposit_info' => 'nullable|array',
            'extra_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validator->errors());
        }

        // 获取支付方式
        $paymentMethod = PaymentMethod::find($request->input('payment_method_id'));
        
        // 验证支付方式存在且是存款类型
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        if ($paymentMethod->type !== PaymentMethod::TYPE_DEPOSIT) {
            return $this->error(ErrorCode::VALIDATION_ERROR, [
                'payment_method_id' => ['Payment method must be of type deposit']
            ]);
        }

        // 验证支付方式是否启用
        if (!$paymentMethod->enabled) {
            return $this->error(ErrorCode::OPERATION_NOT_ALLOWED, 'Payment method is disabled');
        }

        // 验证货币类型匹配
        if ($paymentMethod->currency !== $request->input('currency')) {
            return $this->error(ErrorCode::VALIDATION_ERROR, [
                'currency' => ['Currency does not match payment method']
            ]);
        }

        // 验证金额
        $amount = $request->input('amount');
        if (!$paymentMethod->isAmountValid($amount)) {
            return $this->error(ErrorCode::VALIDATION_ERROR, [
                'amount' => ['Amount is not within the allowed range for this payment method']
            ]);
        }

        // 生成唯一订单号
        $orderNo = 'DEP' . strtoupper(Str::ulid()->toString());

        // 设置过期时间（默认30分钟后过期）
        $expiredAt = Carbon::now()->addMinutes(30);

        // 创建存款订单
        $deposit = Deposit::create([
            'user_id' => $user->id,
            'order_no' => $orderNo,
            'currency' => $request->input('currency'),
            'amount' => $amount,
            'payment_method_id' => $paymentMethod->id,
            'deposit_info' => $request->input('deposit_info'),
            'extra_info' => $request->input('extra_info'),
            'status' => Deposit::STATUS_PENDING,
            'pay_status' => Deposit::PAY_STATUS_PENDING,
            'user_ip' => $request->ip(),
            'expired_at' => $expiredAt,
        ]);

        // 返回创建的存款订单信息
        return $this->responseItem([
            'order_no' => $deposit->order_no,
            'currency' => $deposit->currency,
            'amount' => (float)$deposit->amount,
            'actual_amount' => $deposit->actual_amount ? (float)$deposit->actual_amount : null,
            'payment_method' => [
                'key' => $paymentMethod->key,
                'name' => $paymentMethod->name,
                'display_name' => $paymentMethod->display_name,
            ],
            'status' => $deposit->status,
            'pay_status' => $deposit->pay_status,
            'expired_at' => $deposit->expired_at->format('Y-m-d H:i:s'),
            'created_at' => $deposit->created_at->format('Y-m-d H:i:s'),
            'deposit_info' => $deposit->deposit_info,
            'extra_info' => $deposit->extra_info,
        ]);
    }

    /**
     * 获取存款订单信息
     * 
     * 通过 order_no 查询
     */
    public function show(Request $request, string $orderNo): JsonResponse
    {
        // 获取当前认证用户
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 通过 order_no 查询存款订单
        $deposit = Deposit::where('order_no', $orderNo)
            ->where('user_id', $user->id)
            ->with(['paymentMethod'])
            ->first();

        if (!$deposit) {
            return $this->error(ErrorCode::NOT_FOUND, 'Deposit not found');
        }

        // 返回存款订单详细信息
        return $this->responseItem([
            'order_no' => $deposit->order_no,
            'out_trade_no' => $deposit->out_trade_no,
            'currency' => $deposit->currency,
            'amount' => (float)$deposit->amount,
            'actual_amount' => $deposit->actual_amount ? (float)$deposit->actual_amount : null,
            'pay_fee' => $deposit->pay_fee ? (float)$deposit->pay_fee : null,
            'payment_method' => $deposit->paymentMethod ? [
                'key' => $deposit->paymentMethod->key,
                'name' => $deposit->paymentMethod->name,
                'display_name' => $deposit->paymentMethod->display_name,
                'icon' => $deposit->paymentMethod->icon,
            ] : null,
            'status' => $deposit->status,
            'pay_status' => $deposit->pay_status,
            'user_ip' => $deposit->user_ip,
            'expired_at' => $deposit->expired_at ? $deposit->expired_at->format('Y-m-d H:i:s') : null,
            'finished_at' => $deposit->finished_at ? $deposit->finished_at->format('Y-m-d H:i:s') : null,
            'created_at' => $deposit->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $deposit->updated_at->format('Y-m-d H:i:s'),
            'deposit_info' => $deposit->deposit_info,
            'extra_info' => $deposit->extra_info,
            'is_expired' => $deposit->isExpired(),
        ]);
    }

    /**
     * 获取充值表单字段配置
     * 
     * 根据支付方式返回用户需要填写的表单字段
     */
    public function formFields(Request $request): JsonResponse
    {
        // 获取当前认证用户
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 验证请求参数
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
        ]);

        if ($validator->fails()) {
            return $this->error(ErrorCode::VALIDATION_ERROR, $validator->errors());
        }

        // 获取支付方式
        $paymentMethod = PaymentMethod::find($request->input('payment_method_id'));
        
        // 验证支付方式存在且是存款类型
        if (!$paymentMethod) {
            return $this->error(ErrorCode::NOT_FOUND, 'Payment method not found');
        }

        if ($paymentMethod->type !== PaymentMethod::TYPE_DEPOSIT) {
            return $this->error(ErrorCode::VALIDATION_ERROR, [
                'payment_method_id' => ['Payment method must be of type deposit']
            ]);
        }

        // 验证支付方式是否启用
        if (!$paymentMethod->enabled) {
            return $this->error(ErrorCode::OPERATION_NOT_ALLOWED, 'Payment method is disabled');
        }

        // 返回表单字段配置（目前返回空对象，表单详情从其他地方获取）
        return $this->responseItem([
            'payment_method_key' => $paymentMethod->key,
            'payment_method_name' => $paymentMethod->name,
            'form_fields' => (object)[],
        ]);
    }
}

