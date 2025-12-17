<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Models\Bundle;
use App\Models\BundlePurchase;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BundleService
{
    protected TransactionService $transactionService;
    protected BalanceService $balanceService;
    protected SopayService $sopayService;

    // 固定的双币种
    const CURRENCY_GOLD_COIN = 'GC';
    const CURRENCY_SOCIAL_COIN = 'SC';

    public function __construct()
    {
        $this->transactionService = new TransactionService();
        $this->balanceService = new BalanceService();
        $this->sopayService = new SopayService();
    }

    /**
     * 检查是否为Bundle模式
     */
    public static function isBundleMode(): bool
    {
        return config('app.balance_mode', 'currency') === 'bundle';
    }

    /**
     * 获取可用的Bundle列表（分页）
     */
    public function getAvailableBundlesPaginated(string $currency = 'USD', int $perPage = 20)
    {
        return Bundle::enabled()
            ->byCurrency($currency)
            ->ordered()
            ->paginate($perPage);
    }

    /**
     * 创建购买订单并发起支付
     */
    public function createPurchase(
        int $userId, 
        int $bundleId, 
        int $paymentMethodId, 
        ?string $userIp = null,
        array $depositInfo = [],
        array $extraInfo = [],
        string $nativeApp = ''
    ): array {
        return DB::transaction(function () use ($userId, $bundleId, $paymentMethodId, $userIp, $depositInfo, $extraInfo, $nativeApp) {
            $bundle = Bundle::lockForUpdate()->find($bundleId);
            if (!$bundle || !$bundle->enabled) {
                throw new Exception(ErrorCode::NOT_FOUND, 'Bundle not found or disabled');
            }

            // 检查并减少库存
            if (!$bundle->decrementStock()) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Bundle out of stock');
            }

            $paymentMethod = PaymentMethod::find($paymentMethodId);
            if (!$paymentMethod || !$paymentMethod->enabled) {
                throw new Exception(ErrorCode::NOT_FOUND, 'Payment method not found or disabled');
            }

            // 验证支付方式是否支持该货币
            if ($paymentMethod->currency !== $bundle->currency) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Payment method currency mismatch');
            }

            // 验证支付方式类型
            if ($paymentMethod->type !== PaymentMethod::TYPE_DEPOSIT) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Payment method type must be deposit');
            }

            $currentPrice = $bundle->getCurrentPrice();

            // 创建购买订单
            $purchase = BundlePurchase::create([
                'order_no' => BundlePurchase::generateOrderNo(),
                'user_id' => $userId,
                'bundle_id' => $bundleId,
                'payment_method_id' => $paymentMethodId,
                'gold_coin' => $bundle->gold_coin,
                'social_coin' => $bundle->social_coin,
                'amount' => $currentPrice,
                'currency' => $bundle->currency,
                'status' => BundlePurchase::STATUS_PENDING,
                'pay_status' => BundlePurchase::PAY_STATUS_UNPAID,
                'user_ip' => $userIp,
                'payment_info' => [
                    'deposit_info' => $depositInfo,
                    'extra_info' => $extraInfo,
                ],
            ]);

            // 调用 Sopay 支付
            $sopayResult = $this->initiateSopayPayment($purchase, $paymentMethod, $nativeApp);

            return [
                'purchase' => $purchase->toApiArray(),
                'payment' => $sopayResult,
            ];
        });
    }

    /**
     * 发起 Sopay 支付
     */
    protected function initiateSopayPayment(BundlePurchase $purchase, PaymentMethod $paymentMethod, string $nativeApp): array
    {
        $purchase->load('user');
        
        $data = $this->getSopayDepositData($purchase, $paymentMethod, $nativeApp);
        $endpoint = config('services.sopay.endpoint');
        $url = $endpoint . '/api/v2/orders/deposit';

        Log::info('Sopay bundle purchase request', [
            'url' => $url,
            'order_no' => $purchase->order_no,
            'amount' => $purchase->amount,
            'currency' => $paymentMethod->currency,
            'payment_key' => $paymentMethod->key,
            'user_id' => $purchase->user_id,
        ]);

        $resp = \Illuminate\Support\Facades\Http::post($url, $data);
        $res = $resp->json();

        Log::info('Sopay bundle purchase response', [
            'order_no' => $purchase->order_no,
            'status_code' => $resp->status(),
            'response' => $res,
        ]);

        if (!$res) {
            Log::error('Sopay bundle purchase response empty', [
                'order_no' => $purchase->order_no,
                'status_code' => $resp->status(),
                'body' => $resp->body(),
            ]);
            throw new Exception(ErrorCode::PAY_DEPOSIT_FAILED);
        }

        if ($res['code'] != 0) {
            Log::error('Sopay bundle purchase failed', [
                'order_no' => $purchase->order_no,
                'code' => $res['code'],
                'message' => $res['msg'] ?? null,
            ]);
            throw new Exception(SopayService::ErrorCode[$res['code']] ?? ErrorCode::PAY_DEPOSIT_FAILED);
        }

        $resData = $res['data'];
        $purchase->update(['out_trade_no' => $resData['order_id']]);

        return [
            'url' => $resData['url'],
            'extra_info' => ($resData['extra_info'] ?? null) ?: null,
            'datetime' => time(),
            'html' => $resData['html'] ?? null,
            'order_no' => $purchase->order_no,
        ];
    }

    /**
     * 构建 Sopay 支付请求数据
     */
    protected function getSopayDepositData(BundlePurchase $purchase, PaymentMethod $payment, string $nativeApp): array
    {
        $paymentInfo = $purchase->payment_info ?? [];
        $extraInfo = $paymentInfo['extra_info'] ?? [];
        $depositInfo = $paymentInfo['deposit_info'] ?? [];

        $data = [
            'amount' => $purchase->amount,
            'type' => $payment->is_fiat ? 2 : 1,
            'symbol' => $payment->currency,
            'coin_type' => $payment->currency_type,
            'subject' => 'deposit',
            'out_trade_no' => $purchase->order_no,
            'user_ip' => $purchase->user_ip,
            'payment_id' => $payment->key,
            'channel_id' => $depositInfo['channel_id'] ?? 0,
            'has_native_app' => $nativeApp,
            'method' => 'deposit',
            'callback_url' => config('services.sopay.callback_url'),
            'ua' => request()->header('User-Agent'),
            'user_id' => $purchase->user->uid ?? $purchase->user_id,
        ];

        if ($payment->is_fiat) {
            $data['payment_id'] = (int) $payment->key;
            $channelId = $depositInfo['channel_id'] ?? 0;
            if ($channelId) {
                $data['channel_id'] = (int) $channelId;
            }
        }

        $data['return_url'] = config('services.sopay.return_url');
        
        if (count($extraInfo) > 0) {
            $data['extra_info'] = $this->trimValue($extraInfo);
        }

        // 签名
        $data = $this->signSopayData($data);

        return $data;
    }

    /**
     * Sopay 签名
     */
    protected function signSopayData(array $params): array
    {
        $params['app_id'] = config('services.sopay.app_id');
        $params['timestamp'] = time();
        ksort($params);
        $presign = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $presign = md5($presign);
        $sign = hash_hmac('sha256', $presign, config('services.sopay.app_key'));
        $params['sign'] = $sign;
        return $params;
    }

    /**
     * 清理值
     */
    protected function trimValue(array $info): array
    {
        $data = [];
        foreach ($info as $k => $v) {
            $data[$k] = trim($v);
        }
        return $data;
    }

    /**
     * 完成购买（支付成功回调）
     */
    public function completePurchase(int $purchaseId, ?string $outTradeNo = null, ?array $paymentInfo = null): BundlePurchase
    {
        return DB::transaction(function () use ($purchaseId, $outTradeNo, $paymentInfo) {
            $purchase = BundlePurchase::lockForUpdate()->find($purchaseId);
            
            if (!$purchase) {
                throw new Exception(ErrorCode::NOT_FOUND, 'Purchase not found');
            }

            if ($purchase->isCompleted()) {
                return $purchase; // 幂等处理
            }

            if ($purchase->status === BundlePurchase::STATUS_CANCELLED || 
                $purchase->status === BundlePurchase::STATUS_REFUNDED) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Purchase already cancelled or refunded');
            }

            // 更新购买记录
            $purchase->update([
                'status' => BundlePurchase::STATUS_COMPLETED,
                'pay_status' => BundlePurchase::PAY_STATUS_PAID,
                'out_trade_no' => $outTradeNo ?? $purchase->out_trade_no,
                'paid_at' => now(),
                'finished_at' => now(),
            ]);

            // 增加用户余额
            $this->creditCoins($purchase);

            return $purchase->fresh();
        });
    }

    /**
     * 通过订单号完成购买
     */
    public function completePurchaseByOrderNo(string $orderNo, ?string $outTradeNo = null, ?array $paymentInfo = null): BundlePurchase
    {
        $purchase = BundlePurchase::where('order_no', $orderNo)->first();
        if (!$purchase) {
            throw new Exception(ErrorCode::NOT_FOUND, 'Purchase not found');
        }

        return $this->completePurchase($purchase->id, $outTradeNo, $paymentInfo);
    }

    /**
     * 处理 Sopay 回调
     */
    public function finishBundlePurchase(int $status, string $orderNo, string $outTradeNo, float $amount): bool
    {
        $purchase = BundlePurchase::where('order_no', $orderNo)->first();

        if (!$purchase) {
            Log::error('Bundle purchase not found for callback', [
                'order_no' => $orderNo,
                'out_trade_no' => $outTradeNo,
            ]);
            return false;
        }

        if ($purchase->status !== BundlePurchase::STATUS_PENDING) {
            return true; // 已处理
        }

        switch ($status) {
            case SopayService::SOPAY_STATUS_SUCCEED:
            case SopayService::SOPAY_STATUS_DELAYED:
                $this->completePurchase($purchase->id, $outTradeNo);
                break;

            case SopayService::SOPAY_STATUS_FAILED:
                $purchase->update([
                    'status' => BundlePurchase::STATUS_FAILED,
                    'out_trade_no' => $outTradeNo,
                    'finished_at' => now(),
                ]);
                // 恢复库存
                $this->restoreStock($purchase->bundle_id);
                break;

            case SopayService::SOPAY_STATUS_EXPIRED:
                $purchase->update([
                    'status' => BundlePurchase::STATUS_CANCELLED,
                    'out_trade_no' => $outTradeNo,
                    'finished_at' => now(),
                    'notes' => 'Payment expired',
                ]);
                // 恢复库存
                $this->restoreStock($purchase->bundle_id);
                break;
        }

        return true;
    }

    /**
     * 为用户增加币种余额
     */
    protected function creditCoins(BundlePurchase $purchase): void
    {
        $userId = $purchase->user_id;
        $relatedEntityId = (string) $purchase->id;

        // 增加 GoldCoin
        if ($purchase->gold_coin > 0) {
            $this->balanceService->updateBalance(
                $userId,
                self::CURRENCY_GOLD_COIN,
                (float) $purchase->gold_coin,
                'add',
                'available'
            );

            // 创建交易记录
            $this->transactionService->createTransaction(
                $userId,
                self::CURRENCY_GOLD_COIN,
                (float) $purchase->gold_coin,
                Transaction::TYPE_DEPOSIT,
                $relatedEntityId,
                'Bundle purchase: ' . ($purchase->bundle->name ?? $purchase->bundle_id)
            );
        }

        // 增加 SocialCoin
        if ($purchase->social_coin > 0) {
            $this->balanceService->updateBalance(
                $userId,
                self::CURRENCY_SOCIAL_COIN,
                (float) $purchase->social_coin,
                'add',
                'available'
            );

            // 创建交易记录
            $this->transactionService->createTransaction(
                $userId,
                self::CURRENCY_SOCIAL_COIN,
                (float) $purchase->social_coin,
                Transaction::TYPE_DEPOSIT,
                $relatedEntityId,
                'Bundle purchase: ' . ($purchase->bundle->name ?? $purchase->bundle_id)
            );
        }
    }

    /**
     * 取消购买
     */
    public function cancelPurchase(int $purchaseId): BundlePurchase
    {
        $purchase = BundlePurchase::find($purchaseId);
        
        if (!$purchase) {
            throw new Exception(ErrorCode::NOT_FOUND, 'Purchase not found');
        }

        if (!$purchase->isPending()) {
            throw new Exception(ErrorCode::VALIDATION_ERROR, 'Only pending purchases can be cancelled');
        }

        $purchase->update([
            'status' => BundlePurchase::STATUS_CANCELLED,
            'finished_at' => now(),
        ]);

        // 恢复库存
        $this->restoreStock($purchase->bundle_id);

        return $purchase->fresh();
    }

    /**
     * 恢复库存
     */
    protected function restoreStock(int $bundleId): void
    {
        $bundle = Bundle::find($bundleId);
        if ($bundle) {
            $bundle->incrementStock();
        }
    }

    /**
     * 获取用户的购买记录（分页）
     */
    public function getUserPurchasesPaginated(int $userId, int $perPage = 20)
    {
        return BundlePurchase::forUser($userId)
            ->with(['bundle', 'paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * 获取购买详情
     */
    public function getPurchase(int $purchaseId): ?BundlePurchase
    {
        return BundlePurchase::with(['bundle', 'paymentMethod'])->find($purchaseId);
    }

    /**
     * 通过订单号获取购买详情
     */
    public function getPurchaseByOrderNo(string $orderNo): ?BundlePurchase
    {
        return BundlePurchase::with(['bundle', 'paymentMethod'])
            ->where('order_no', $orderNo)
            ->first();
    }

    /**
     * 获取支付表单字段（复用 DepositService）
     */
    public function getFormFields(float $amount, PaymentMethod $paymentMethod): array
    {
        return $this->sopayService->getDepositInfo($amount, $paymentMethod);
    }
}
