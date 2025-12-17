<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Models\Bundle;
use App\Models\BundlePurchase;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BundleService
{
    protected TransactionService $transactionService;
    protected BalanceService $balanceService;

    // 固定的双币种
    const CURRENCY_GOLD_COIN = 'GC';
    const CURRENCY_SOCIAL_COIN = 'SC';

    public function __construct()
    {
        $this->transactionService = new TransactionService();
        $this->balanceService = new BalanceService();
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
     * 创建购买订单
     */
    public function createPurchase(int $userId, int $bundleId, int $paymentMethodId, ?string $userIp = null): BundlePurchase
    {
        $bundle = Bundle::findCached($bundleId);
        if (!$bundle || !$bundle->enabled) {
            throw new Exception(ErrorCode::NOT_FOUND, 'Bundle not found or disabled');
        }

        // 检查库存
        if (!$bundle->hasStock()) {
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

        return BundlePurchase::create([
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
        ]);
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

            // 减少库存
            $bundle = Bundle::lockForUpdate()->find($purchase->bundle_id);
            if ($bundle && !$bundle->decrementStock()) {
                throw new Exception(ErrorCode::VALIDATION_ERROR, 'Bundle out of stock');
            }

            // 更新购买记录
            $purchase->update([
                'status' => BundlePurchase::STATUS_COMPLETED,
                'pay_status' => BundlePurchase::PAY_STATUS_PAID,
                'out_trade_no' => $outTradeNo,
                'payment_info' => $paymentInfo,
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

        return $purchase->fresh();
    }

    /**
     * 获取用户的购买记录
     */
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

}

