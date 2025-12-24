<?php

namespace App\Services;

use App\Models\Withdraw;
use App\Models\PaymentMethod;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Exceptions\Exception;
use App\Enums\ErrorCode;
use Carbon\Carbon;

class WithdrawService
{
    protected $balanceService;

    public function __construct()
    {
        $this->balanceService = new BalanceService();
    }

    /**
     * Get user withdraws with filters and pagination.
     *
     * @param int $userId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserWithdraws(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Withdraw::query()
            ->where('user_id', $userId)
            ->with(['paymentMethod'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['pay_status'])) {
            $query->where('pay_status', $filters['pay_status']);
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (isset($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Format withdraw for API response.
     *
     * @param Withdraw $withdraw
     * @param bool $includeDetails Include additional details like user_ip, updated_at, etc.
     * @return array
     */
    public function formatWithdrawForResponse(Withdraw $withdraw, bool $includeDetails = false): array
    {
        $data = [
            'order_no' => $withdraw->order_no,
            'currency' => $withdraw->currency,
            'amount' => (float)$withdraw->amount,
            'actual_amount' => $withdraw->actual_amount ? (float)$withdraw->actual_amount : null,
            'fee' => $withdraw->fee ? (float)$withdraw->fee : null,
            'payment_method' => $withdraw->paymentMethod ? [
                'key' => $withdraw->paymentMethod->key,
                'name' => $withdraw->paymentMethod->name,
                'display_name' => $withdraw->paymentMethod->display_name,
                'icon' => $withdraw->paymentMethod->icon ?? null,
            ] : null,
            'status' => $withdraw->status,
            'pay_status' => $withdraw->pay_status,
            'approved' => $withdraw->approved,
            'completed_at' => $withdraw->completed_at ? $withdraw->completed_at->format('Y-m-d H:i:s') : null,
            'created_at' => $withdraw->created_at->format('Y-m-d H:i:s'),
        ];

        // Include out_trade_no in list view
        if ($withdraw->out_trade_no) {
            $data['out_trade_no'] = $withdraw->out_trade_no;
        }

        if ($includeDetails) {
            $data['user_ip'] = $withdraw->user_ip;
            $data['updated_at'] = $withdraw->updated_at->format('Y-m-d H:i:s');
            $data['withdraw_info'] = $withdraw->withdraw_info;
            $data['extra_info'] = $withdraw->extra_info;
            $data['note'] = $withdraw->note;
        }

        return $data;
    }

    /**
     * Get withdraw by order number for a specific user.
     *
     * @param int $userId
     * @param string $orderNo
     * @return Withdraw|null
     */
    public function getWithdrawByOrderNo(int $userId, string $orderNo): ?Withdraw
    {
        return Withdraw::where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->with(['paymentMethod'])
            ->first();
    }

    /**
     * Validate payment method for withdraw.
     *
     * @param PaymentMethod $paymentMethod
     * @param string $currency
     * @param float $amount
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validatePaymentMethod(PaymentMethod $paymentMethod, string $currency, float $amount): array
    {
        $errors = [];

        // Check if payment method is withdraw type
        if ($paymentMethod->type !== PaymentMethod::TYPE_WITHDRAW) {
            $errors['payment_method_id'] = ['Payment method must be of type withdraw'];
        }

        // Check if payment method is enabled
        if (!$paymentMethod->enabled) {
            $errors['payment_method'] = ['Payment method is disabled'];
        }

        // Check currency match
        if ($paymentMethod->currency !== $currency) {
            $errors['currency'] = ['Currency does not match payment method'];
        }

        // Check amount validity
        if (!$paymentMethod->isAmountValid($amount)) {
            $errors['amount'] = ['Amount is not within the allowed range for this payment method'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Create a new withdraw order.
     *
     * @param int $userId
     * @param PaymentMethod $paymentMethod
     * @param string $currency
     * @param float $amount
     * @param array $withdrawInfo
     * @param array $extraInfo
     * @param string $userIp
     * @return Withdraw
     */
    public function createWithdraw(
        int $userId,
        PaymentMethod $paymentMethod,
        string $currency,
        float $amount,
        array $withdrawInfo = [],
        array $extraInfo = [],
        string $userIp = ''
    ): Withdraw {
        return DB::transaction(function () use ($userId, $paymentMethod, $currency, $amount, $withdrawInfo, $extraInfo, $userIp) {
            // Generate unique order number
            $orderNo = 'WTD' . strtoupper(Str::ulid()->toString());

            // Create withdraw order first (with PENDING status)
            $withdraw = Withdraw::create([
                'user_id' => $userId,
                'order_no' => $orderNo,
                'currency' => $currency,
                'amount' => $amount,
                'payment_method_id' => $paymentMethod->id,
                'withdraw_info' => $withdrawInfo,
                'extra_info' => $extraInfo,
                'status' => Withdraw::STATUS_PENDING,
                'pay_status' => Withdraw::PAY_STATUS_PENDING,
                'approved' => false,
                'fee' => 0.00,
                'user_ip' => $userIp,
            ]);

            // Freeze balance for this withdraw request
            try {
                $notes = "Withdraw request #{$orderNo}";
                $this->balanceService->requestWithdraw(
                    $userId,
                    $currency,
                    $amount,
                    $notes,
                    $withdraw->id
                );
            } catch (\Exception $e) {
                // If balance freeze fails, throw exception to rollback transaction
                throw new Exception(ErrorCode::INSUFFICIENT_BALANCE, $e->getMessage());
            }

            return $withdraw;
        });
    }

    /**
     * Get payment method by ID.
     *
     * @param int $paymentMethodId
     * @return PaymentMethod|null
     */
    public function getPaymentMethod(int $paymentMethodId): ?PaymentMethod
    {
        return PaymentMethod::find($paymentMethodId);
    }

    /**
     * Validate payment method basic info (type and enabled status).
     *
     * @param PaymentMethod $paymentMethod
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validatePaymentMethodBasic(PaymentMethod $paymentMethod): array
    {
        $errors = [];

        // Check if payment method is withdraw type
        if ($paymentMethod->type !== PaymentMethod::TYPE_WITHDRAW) {
            $errors['payment_method_id'] = ['Payment method must be of type withdraw'];
        }

        // Check if payment method is enabled
        if (!$paymentMethod->enabled) {
            $errors['payment_method'] = ['Payment method is disabled'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get form fields configuration for payment method.
     *
     * @param float $amount
     * @param PaymentMethod $paymentMethod
     * @return array
     */
    public function getFormFields($amount, PaymentMethod $paymentMethod)
    {
        $sopayService = new SopayService();
        $fields = $sopayService->getWithdrawInfo($amount, $paymentMethod);
 
        return $fields;
    }

    public function finishWithdraw($orderId, $outId, $amount)
    {
        $withdraw = Withdraw::where('order_no', $orderId)->where('out_id', $outId)->first();
        if(!$withdraw) {
            return false;
        }

        // 更新最后回调时间
        $withdraw->update(['last_callback_at' => Carbon::now()]);

        return DB::transaction(function () use ($withdraw, $amount) {
            $withdraw->update([
                'status' => Withdraw::STATUS_COMPLETED,
                'pay_status' => Withdraw::PAY_STATUS_PAID,
                'completed_at' => Carbon::now(),
            ]);
            $this->balanceService->finishWithdraw($withdraw->user_id, $withdraw->currency, $amount, 'Withdraw', $withdraw->id);
            return true;
        });
    }

    public function failWithdraw($orderId, $outId, $errorMessage, $payStatus)
    {
        $withdraw = Withdraw::where('order_no', $orderId)->where('out_id', $outId)->first();
        if(!$withdraw) {
            return false;
        }

        // 更新最后回调时间
        $withdraw->update(['last_callback_at' => Carbon::now()]);

        return DB::transaction(function () use ($withdraw, $errorMessage, $payStatus) {
            $withdraw->update([
                'status' => Withdraw::STATUS_FAILED,
                'pay_status' => $payStatus,
                'pay_error' => $errorMessage,
            ]);
            // 解冻用户余额（提现请求时已冻结）
            $this->balanceService->unfreezeAmount($withdraw->user_id, $withdraw->currency, $withdraw->amount);
            return true;
        });
    }
}

