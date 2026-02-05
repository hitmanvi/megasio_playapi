<?php

namespace App\Services;

use App\Events\DepositCompleted;
use App\Models\Deposit;
use App\Models\PaymentMethod;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exceptions\Exception;
use App\Enums\ErrorCode;
use Illuminate\Support\Facades\Log;

class DepositService
{
    protected $balanceService;

    public function __construct()
    {
        $this->balanceService = new BalanceService();
    }

    /**
     * Get user deposits with filters and pagination.
     *
     * @param int $userId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserDeposits(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Deposit::query()
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
     * Format deposit for API response.
     *
     * @param Deposit $deposit
     * @param bool $includeDetails Include additional details like user_ip, updated_at, etc.
     * @return array
     */
    public function formatDepositForResponse(Deposit $deposit, bool $includeDetails = false): array
    {
        $data = [
            'order_no' => $deposit->order_no,
            'currency' => $deposit->currency,
            'amount' => (float)$deposit->amount,
            'actual_amount' => $deposit->actual_amount ? (float)$deposit->actual_amount : null,
            'pay_fee' => $deposit->pay_fee ? (float)$deposit->pay_fee : null,
            'payment_method' => $deposit->paymentMethod ? [
                'key' => $deposit->paymentMethod->key,
                'name' => $deposit->paymentMethod->name,
                'display_name' => $deposit->paymentMethod->display_name,
                'icon' => $deposit->paymentMethod->icon ?? null,
            ] : null,
            'status' => $deposit->status,
            'pay_status' => $deposit->pay_status,
            'expired_at' => $deposit->expired_at ? $deposit->expired_at->format('Y-m-d H:i:s') : null,
            'completed_at' => $deposit->completed_at ? $deposit->completed_at->format('Y-m-d H:i:s') : null,
            'created_at' => $deposit->created_at->format('Y-m-d H:i:s'),
            'is_expired' => $deposit->isExpired(),
        ];

        // Include out_trade_no in list view
        if ($deposit->out_trade_no) {
            $data['out_trade_no'] = $deposit->out_trade_no;
        }

        if ($includeDetails) {
            $data['user_ip'] = $deposit->user_ip;
            $data['updated_at'] = $deposit->updated_at->format('Y-m-d H:i:s');
            $data['deposit_info'] = $deposit->deposit_info;
            $data['extra_info'] = $deposit->extra_info;
        }

        return $data;
    }

    /**
     * Get deposit by order number for a specific user.
     *
     * @param int $userId
     * @param string $orderNo
     * @return Deposit|null
     */
    public function getDepositByOrderNo(int $userId, string $orderNo): ?Deposit
    {
        return Deposit::where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->with(['paymentMethod'])
            ->first();
    }

    /**
     * Validate payment method for deposit.
     *
     * @param PaymentMethod $paymentMethod
     * @param string $currency
     * @param float $amount
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validatePaymentMethod(PaymentMethod $paymentMethod, string $currency, float $amount): array
    {
        $errors = [];

        // Check if payment method is deposit type
        if ($paymentMethod->type !== PaymentMethod::TYPE_DEPOSIT) {
            $errors['payment_method_id'] = ['Payment method must be of type deposit'];
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
     * Create a new deposit order.
     *
     * @param int $userId
     * @param PaymentMethod $paymentMethod
     * @param string $currency
     * @param float $amount
     * @param array $depositInfo
     * @param array $extraInfo
     * @param string $userIp
     * @param int|null $expireMinutes
     * @param string $nativeApp
     * @return array Sopay response with url, extra_info, datetime, html, order_no
     */
    public function createDeposit(
        int $userId,
        PaymentMethod $paymentMethod,
        string $currency,
        float $amount,
        array $depositInfo = [],
        array $extraInfo = [],
        string $userIp = '',
        ?int $expireMinutes = 30,
        string $nativeApp = ''
    ): array {
        // Generate unique order number
        $orderNo = 'DEP' . strtoupper(Str::ulid()->toString());

        // Set expiration time
        $expiredAt = Carbon::now()->addMinutes($expireMinutes);

        // Create deposit order
        $deposit = Deposit::create([
            'user_id' => $userId,
            'order_no' => $orderNo,
            'currency' => $currency,
            'amount' => $amount,
            'payment_method_id' => $paymentMethod->id,
            'deposit_info' => $depositInfo,
            'extra_info' => $extraInfo,
            'status' => Deposit::STATUS_PROCESSING,
            'pay_status' => SopayService::SOPAY_STATUS_PREPARING,
            'user_ip' => $userIp,
            'expired_at' => $expiredAt,
        ]);

        $res = $this->deposit($deposit, $paymentMethod, $nativeApp);
        if (!$res) {
            throw new Exception(ErrorCode::PAY_DEPOSIT_FAILED);
        }

        // Return sopay response with order_no
        $res['order_no'] = $deposit->order_no;
        return $res;
    }

    public function deposit(Deposit $deposit, PaymentMethod $paymentMethod, string $nativeApp)
    {
        $sopayService = new SopayService();
        $res = $sopayService->deposit($deposit, $paymentMethod, $nativeApp);
        if(!$res) {
            throw new Exception(ErrorCode::INTERNAL_ERROR);
        }

        return $res;
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

        // Check if payment method is deposit type
        if ($paymentMethod->type !== PaymentMethod::TYPE_DEPOSIT) {
            $errors['payment_method_id'] = ['Payment method must be of type deposit'];
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
     * @param PaymentMethod $paymentMethod
     * @return array
     */
    public function getFormFields($amount, PaymentMethod $paymentMethod)
    {
        $sopayService = new SopayService();
        $fields = $sopayService->getDepositInfo($amount, $paymentMethod);
 
        return $fields;
    }

    public function getExtraStepFields($amount, PaymentMethod $paymentMethod, $extraInfo)
    {
        $sopayService = new SopayService();
        $fields = $sopayService->getExtraStepInfo($amount, $paymentMethod, $extraInfo);
        if(!$fields) {
            throw new Exception(ErrorCode::INTERNAL_ERROR);
        }

        return $fields;
    }

    public function finishDeposit($status, $orderId, $outId, $amount)
    {
        $deposit = Deposit::where('order_no', $orderId)
            ->where('out_trade_no', $outId)
            ->first();

        if (!$deposit) {
            return false;
        }

        // 更新最后回调时间
        $deposit->update(['last_callback_at' => Carbon::now()]);

        // 如果 deposit 已经是完成状态，直接返回，避免重复处理
        if ($deposit->status === Deposit::STATUS_COMPLETED) {
            return true;
        }

        // 如果状态不是 PROCESSING，直接返回
        if ($deposit->status !== Deposit::STATUS_PROCESSING) {
            return true;
        }

        $updateData = [
            'pay_status' => $status,
            'completed_at' => Carbon::now(),
        ];

        switch ($status) {
            case SopayService::SOPAY_STATUS_SUCCEED:
            case (SopayService::SOPAY_STATUS_DELAYED && $deposit->amount <= $amount):
                $updateData['status'] = Deposit::STATUS_COMPLETED;
                $updateData['amount'] = $amount;
                $deposit->update($updateData);
                
                // 重新加载 deposit 以确保状态已更新
                $deposit->refresh();
                
                // 只有在状态确实是 COMPLETED 时才触发事件和添加余额
                if ($deposit->status === Deposit::STATUS_COMPLETED) {
                    $this->balanceService->deposit(
                        $deposit->user_id,
                        $deposit->currency,
                        $amount,
                        'Deposit',
                        $deposit->id
                    );
                    event(new DepositCompleted($deposit));
                }
                break;
            case SopayService::SOPAY_STATUS_FAILED:
                $updateData['status'] = Deposit::STATUS_FAILED;
                $deposit->update($updateData);
                break;
            case SopayService::SOPAY_STATUS_EXPIRED:
                $updateData['status'] = Deposit::STATUS_EXPIRED;
                $deposit->update($updateData);
                break;
        }

        return true;
    }
}

