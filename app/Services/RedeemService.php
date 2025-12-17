<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Models\PaymentMethod;
use App\Models\Redeem;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedeemService
{
    protected BalanceService $balanceService;
    protected SopayService $sopayService;

    // SC 货币代码
    const SC_CURRENCY = 'SC';
    const USD_CURRENCY = 'USD';

    public function __construct()
    {
        $this->balanceService = new BalanceService();
        $this->sopayService = new SopayService();
    }

    /**
     * 获取当前 SC -> USD 兑换汇率
     */
    public function getExchangeRate(): float
    {
        return (float) config('app.sc_to_usd_rate', 1.0);
    }

    /**
     * 计算 SC 兑换 USD 的金额
     */
    public function calculateUsdAmount(float $scAmount): array
    {
        $rate = $this->getExchangeRate();
        $usdAmount = bcmul((string) $scAmount, (string) $rate, 8);
        
        return [
            'sc_amount' => $scAmount,
            'exchange_rate' => $rate,
            'usd_amount' => (float) $usdAmount,
        ];
    }

    /**
     * 获取用户的兑换记录（分页）
     */
    public function getUserRedeems(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Redeem::query()
            ->where('user_id', $userId)
            ->with(['paymentMethod'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['pay_status'])) {
            $query->where('pay_status', $filters['pay_status']);
        }

        return $query->paginate($perPage);
    }

    /**
     * 格式化兑换记录用于 API 响应
     */
    public function formatRedeemForResponse(Redeem $redeem, bool $includeDetails = false): array
    {
        $data = [
            'order_no' => $redeem->order_no,
            'sc_amount' => (float) $redeem->sc_amount,
            'exchange_rate' => (float) $redeem->exchange_rate,
            'usd_amount' => (float) $redeem->usd_amount,
            'actual_amount' => $redeem->actual_amount ? (float) $redeem->actual_amount : null,
            'fee' => $redeem->fee ? (float) $redeem->fee : null,
            'payment_method' => $redeem->paymentMethod ? [
                'key' => $redeem->paymentMethod->key,
                'name' => $redeem->paymentMethod->name,
                'display_name' => $redeem->paymentMethod->display_name,
                'icon' => $redeem->paymentMethod->icon ?? null,
            ] : null,
            'status' => $redeem->status,
            'pay_status' => $redeem->pay_status,
            'approved' => $redeem->approved,
            'completed_at' => $redeem->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $redeem->created_at->format('Y-m-d H:i:s'),
        ];

        if ($redeem->out_trade_no) {
            $data['out_trade_no'] = $redeem->out_trade_no;
        }

        if ($includeDetails) {
            $data['user_ip'] = $redeem->user_ip;
            $data['updated_at'] = $redeem->updated_at->format('Y-m-d H:i:s');
            $data['withdraw_info'] = $redeem->withdraw_info;
            $data['extra_info'] = $redeem->extra_info;
            $data['note'] = $redeem->note;
        }

        return $data;
    }

    /**
     * 根据订单号获取兑换记录
     */
    public function getRedeemByOrderNo(int $userId, string $orderNo): ?Redeem
    {
        return Redeem::where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->with(['paymentMethod'])
            ->first();
    }

    /**
     * 获取支付方式
     */
    public function getPaymentMethod(int $paymentMethodId): ?PaymentMethod
    {
        return PaymentMethod::find($paymentMethodId);
    }

    /**
     * 验证支付方式
     */
    public function validatePaymentMethod(PaymentMethod $paymentMethod, float $usdAmount): array
    {
        $errors = [];

        // 检查支付方式类型 (必须是提款类型)
        if ($paymentMethod->type !== PaymentMethod::TYPE_WITHDRAW) {
            $errors['payment_method_id'] = ['Payment method must be of type withdraw'];
        }

        // 检查支付方式是否启用
        if (!$paymentMethod->enabled) {
            $errors['payment_method'] = ['Payment method is disabled'];
        }

        // 检查货币是否匹配 (必须是 USD)
        if ($paymentMethod->currency !== self::USD_CURRENCY) {
            $errors['currency'] = ['Payment method currency must be USD'];
        }

        // 检查金额范围
        if (!$paymentMethod->isAmountValid($usdAmount)) {
            $errors['amount'] = ['Amount is not within the allowed range for this payment method'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 创建兑换订单
     */
    public function createRedeem(
        int $userId,
        float $scAmount,
        PaymentMethod $paymentMethod,
        array $withdrawInfo = [],
        array $extraInfo = [],
        string $userIp = ''
    ): Redeem {
        return DB::transaction(function () use ($userId, $scAmount, $paymentMethod, $withdrawInfo, $extraInfo, $userIp) {
            // 计算兑换金额
            $calculation = $this->calculateUsdAmount($scAmount);
            
            // 生成订单号
            $orderNo = 'RDM' . strtoupper(Str::ulid()->toString());

            // 创建兑换订单
            $redeem = Redeem::create([
                'user_id' => $userId,
                'order_no' => $orderNo,
                'sc_amount' => $scAmount,
                'exchange_rate' => $calculation['exchange_rate'],
                'usd_amount' => $calculation['usd_amount'],
                'payment_method_id' => $paymentMethod->id,
                'withdraw_info' => $withdrawInfo,
                'extra_info' => $extraInfo,
                'status' => Redeem::STATUS_PENDING,
                'pay_status' => Redeem::PAY_STATUS_PENDING,
                'approved' => false,
                'fee' => 0.00,
                'user_ip' => $userIp,
            ]);

            // 冻结 SC 余额
            try {
                $notes = "Redeem request #{$orderNo}";
                $this->balanceService->requestWithdraw(
                    $userId,
                    self::SC_CURRENCY,
                    $scAmount,
                    $notes,
                    $redeem->id
                );
            } catch (\Exception $e) {
                throw new Exception(ErrorCode::INSUFFICIENT_BALANCE, $e->getMessage());
            }

            return $redeem;
        });
    }

    /**
     * 完成兑换 (回调处理)
     */
    public function finishRedeem(string $orderNo, string $outTradeNo, float $amount): bool
    {
        $redeem = Redeem::where('order_no', $orderNo)->first();
        if (!$redeem) {
            return false;
        }

        return DB::transaction(function () use ($redeem, $outTradeNo, $amount) {
            $redeem->update([
                'out_trade_no' => $outTradeNo,
                'actual_amount' => $amount,
                'status' => Redeem::STATUS_COMPLETED,
                'pay_status' => Redeem::PAY_STATUS_PAID,
                'completed_at' => Carbon::now(),
            ]);

            // 完成 SC 扣款
            $this->balanceService->finishWithdraw(
                $redeem->user_id,
                self::SC_CURRENCY,
                $redeem->sc_amount,
                "Redeem #{$redeem->order_no}",
                $redeem->id
            );

            return true;
        });
    }

    /**
     * 取消/失败兑换 (回调处理)
     */
    public function failRedeem(string $orderNo, string $outTradeNo = ''): bool
    {
        $redeem = Redeem::where('order_no', $orderNo)->first();
        if (!$redeem) {
            return false;
        }

        if ($redeem->status !== Redeem::STATUS_PENDING) {
            return false;
        }

        return DB::transaction(function () use ($redeem, $outTradeNo) {
            $redeem->update([
                'out_trade_no' => $outTradeNo ?: $redeem->out_trade_no,
                'status' => Redeem::STATUS_FAILED,
                'pay_status' => Redeem::PAY_STATUS_FAILED,
            ]);

            // 解冻 SC 余额，退回可用余额
            $this->balanceService->unfreezeAmount(
                $redeem->user_id,
                self::SC_CURRENCY,
                (float) $redeem->sc_amount
            );

            return true;
        });
    }

    /**
     * 获取表单字段配置
     */
    public function getFormFields(float $usdAmount, PaymentMethod $paymentMethod): array
    {
        return $this->sopayService->getWithdrawInfo($usdAmount, $paymentMethod);
    }
}

