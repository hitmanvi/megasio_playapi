<?php

namespace App\Services;

use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\GameNotFoundException;
use App\Exceptions\GameNotEnabledException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProviderTransactionNotFoundException;
use App\Models\Game;
use App\Models\Order;
use App\Models\ProviderTransaction;
use Illuminate\Support\Facades\DB;
use App\Services\GameProviderTokenService;

class ProviderCallbackService
{
    protected ProviderTransactionService $providerTransactionService;
    protected BalanceService $balanceService;
    protected OrderService $orderService;
    protected GameProviderTokenService $tokenService;

    public function __construct()
    {
        $this->providerTransactionService = new ProviderTransactionService();
        $this->balanceService = new BalanceService();
        $this->orderService = new OrderService();
        $this->tokenService = new GameProviderTokenService();
    }

    /**
     * 处理 bet 回调
     *
     * @param string $provider 提供商标识
     * @param string $gameOutId 游戏外部ID（out_id）
     * @param string $token Token值
     * @param string $txid 交易ID
     * @param string $roundId 回合ID
     * @param float $amount 金额
     * @param array $detail 详细信息
     * @return array 包含 transaction, balance, order 的数组
     * @throws DuplicateTransactionException
     * @throws GameNotFoundException
     * @throws GameNotEnabledException
     * @throws InvalidTokenException
     */
    public function handleBet(
        string $provider,
        string $gameOutId,
        string $token,
        string $txid,
        string $roundId,
        float $amount,
        array $detail
    ): array {
        // 检查是否已存在
        $existing = $this->providerTransactionService->findByProviderAndTxid($provider, $txid);
        if ($existing) {
            throw new DuplicateTransactionException();
        }

        // 根据 token 获取用户信息
        $userInfo = $this->getUserInfoByToken($token);
        $userId = $userInfo['user_id'];
        $currency = $userInfo['currency'];

        // 根据 gameOutId 查询游戏
        $game = Game::where('out_id', $gameOutId)->first();
        if (!$game) {
            throw new GameNotFoundException();
        }
        if (!$game->enabled) {
            throw new GameNotEnabledException();
        }

        return DB::transaction(function () use ($provider, $game, $userId, $txid, $roundId, $amount, $currency, $detail) {
            // 创建 provider transaction 记录
            $transaction = $this->providerTransactionService->create(
                $provider,
                $game->id,
                $userId,
                $txid,
                $roundId,
                $detail
            );

            // 处理余额扣减
            $result = $this->balanceService->bet($userId, $amount, $currency, $game->id, $txid);
            $balance = $result['balance']['available'];

            // 创建或更新订单
            $order = $this->orderService->bet($userId, $amount, $currency, $game, $roundId);

            // 更新 provider transaction 的 order_id
            $this->providerTransactionService->updateOrderId($transaction, $order->id);

            return [
                'transaction' => $transaction,
                'balance' => floatval($balance),
                'order' => $order,
            ];
        });
    }

    /**
     * 处理 payout (win) 回调
     *
     * @param string $provider 提供商标识
     * @param string $txid 交易ID
     * @param string $roundId 回合ID
     * @param float $amount 金额
     * @param array $detail 详细信息
     * @param bool $isFinished 是否完成
     * @return ProviderTransaction
     * @throws DuplicateTransactionException
     * @throws OrderNotFoundException
     * @throws GameNotFoundException
     */
    public function handlePayout(
        string $provider,
        string $txid,
        string $roundId,
        float $amount,
        array $detail,
        bool $isFinished = true
    ): array {
        // 检查是否已存在
        $existing = $this->providerTransactionService->findByProviderAndTxid($provider, $txid);
        if ($existing) {
            throw new DuplicateTransactionException();
        }

        // 根据 roundId 获取订单
        $order = Order::where('out_id', $roundId)->first();
        if (!$order) {
            throw new OrderNotFoundException();
        }

        // 从订单中获取用户ID、游戏ID和货币
        $userId = $order->user_id;
        $gameId = $order->game_id;
        $currency = $order->currency;

        return DB::transaction(function () use ($provider, $gameId, $userId, $txid, $roundId, $amount, $currency, $detail, $isFinished, $order) {
            // 创建 provider transaction 记录
            $providerTransaction = $this->providerTransactionService->create(
                $provider,
                $gameId,
                $userId,
                $txid,
                $roundId,
                $detail,
                $order->id
            );

            // 处理余额增加
            $result = $this->balanceService->payout($userId, $amount, $currency, $gameId, $txid);
            $balance = $result['balance']['available'];

            // 更新订单
            $this->orderService->payout($order, $amount, $isFinished);

            return [
                'transaction' => $providerTransaction,
                'balance' => floatval($balance),
                'order' => $order,
            ];
        });
    }

    /**
     * 处理 refund 回调
     *
     * @param string $provider 提供商标识
     * @param string $txid 交易ID
     * @param string $roundId 回合ID
     * @param float $amount 金额
     * @param array $detail 详细信息
     * @return ProviderTransaction
     * @throws DuplicateTransactionException
     * @throws OrderNotFoundException
     * @throws GameNotFoundException
     */
    public function handleRefund(
        string $provider,
        string $txid,
        string $roundId,
        float $amount,
        array $detail
    ): ProviderTransaction {
        // 检查是否已存在
        $existing = $this->providerTransactionService->findByProviderAndTxid($provider, $txid);
        if ($existing) {
            throw new DuplicateTransactionException();
        }

        // 根据 roundId 获取订单
        $order = Order::where('out_id', $roundId)->first();
        if (!$order) {
            throw new OrderNotFoundException();
        }

        // 从订单中获取用户ID、游戏ID和货币
        $userId = $order->user_id;
        $gameId = $order->game_id;
        $currency = $order->currency;
        if ($amount == 0 || $amount > $order->amount) {
            $amount = $order->amount;
        }

        return DB::transaction(function () use ($provider, $gameId, $userId, $txid, $roundId, $amount, $currency, $detail, $order) {
            // 创建 provider transaction 记录
            $providerTransaction = $this->providerTransactionService->create(
                $provider,
                $gameId,
                $userId,
                $txid,
                $roundId,
                $detail,
                $order->id
            );

            // 处理余额退款
            $this->balanceService->refund($userId, $amount, $currency, $gameId, $txid);

            // 更新订单
            $this->orderService->refund($order);

            return $providerTransaction;
        });
    }

    /**
     * 根据 token 获取用户信息
     *
     * @param string $token Token值
     * @return array 包含 user_id 和 currency 的数组
     * @throws InvalidTokenException 当 token 无效时抛出异常
     */
    public function getUserInfoByToken(string $token): array
    {
        $tokenRecord = $this->tokenService->verify($token);
        
        if (!$tokenRecord) {
            throw new InvalidTokenException('Invalid or expired token');
        }

        return [
            'user_id' => $tokenRecord->user_id,
            'currency' => $tokenRecord->currency,
        ];
    }

    public function getBalance(string $token): float
    {
        $userInfo = $this->getUserInfoByToken($token);
        $userId = $userInfo['user_id'];
        $currency = $userInfo['currency'];
        $balance = $this->balanceService->getBalance($userId, $currency);
        
        return floatval($balance->available);
    }

    /**
     * 根据 provider 和 txid 获取 Provider Transaction
     *
     * @param string $provider 提供商标识
     * @param string $txid 交易ID
     * @return ProviderTransaction
     * @throws ProviderTransactionNotFoundException 当交易记录不存在时抛出异常
     */
    public function getProviderTransactionById(string $provider, string $txid): ProviderTransaction
    {
        $transaction = $this->providerTransactionService->findByProviderAndTxid($provider, $txid);
        
        if (!$transaction) {
            throw new ProviderTransactionNotFoundException('Provider transaction not found');
        }
        
        return $transaction;
    }
}

