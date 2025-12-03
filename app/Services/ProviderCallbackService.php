<?php

namespace App\Services;

use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\GameNotFoundException;
use App\Exceptions\GameNotEnabledException;
use App\Exceptions\InvalidTokenException;
use App\Models\Game;
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
     * @param int $gameId 游戏ID
     * @param int $userId 用户ID
     * @param string $txid 交易ID
     * @param string $roundId 回合ID
     * @param float $amount 金额
     * @param string $currency 货币类型
     * @param array $detail 详细信息
     * @return ProviderTransaction
     * @throws DuplicateTransactionException
     * @throws GameNotFoundException
     * @throws GameNotEnabledException
     */
    public function handleBet(
        string $provider,
        int $gameId,
        int $userId,
        string $txid,
        string $roundId,
        float $amount,
        string $currency,
        array $detail
    ): ProviderTransaction {
        // 检查是否已存在
        $existing = $this->providerTransactionService->findByProviderAndTxid($provider, $txid);
        if ($existing) {
            throw new DuplicateTransactionException('Duplicate bet transaction');
        }

        return DB::transaction(function () use ($provider, $gameId, $userId, $txid, $roundId, $amount, $currency, $detail) {
            // 创建 provider transaction 记录
            $transaction = $this->providerTransactionService->create(
                $provider,
                $gameId,
                $userId,
                $txid,
                $roundId,
                $detail
            );

            // 处理余额扣减
            $result = $this->balanceService->bet($userId, $amount, $currency, $gameId, $txid);
            $balance = $result['balance'];

            // 创建或更新订单
            $game = Game::find($gameId);
            if (!$game) {
                throw new GameNotFoundException('Game not found');
            }
            if (!$game->enabled) {
                throw new GameNotEnabledException('Game is not enabled');
            }
            $order = $this->orderService->bet($userId, $amount, $currency, $game, $roundId);

            // 更新 provider transaction 的 order_id
            $this->providerTransactionService->updateOrderId($transaction, $order->id);

            return [
                'transaction' => $transaction,
                'balance' => $balance,
                'order' => $order,
            ];
        });
    }

    /**
     * 处理 payout (win) 回调
     *
     * @param string $provider 提供商标识
     * @param int $gameId 游戏ID
     * @param int $userId 用户ID
     * @param string $txid 交易ID
     * @param string $roundId 回合ID
     * @param float $amount 金额
     * @param string $currency 货币类型
     * @param array $detail 详细信息
     * @param bool $isFinished 是否完成
     * @return ProviderTransaction
     * @throws DuplicateTransactionException
     * @throws GameNotFoundException
     */
    public function handlePayout(
        string $provider,
        int $gameId,
        int $userId,
        string $txid,
        string $roundId,
        float $amount,
        string $currency,
        array $detail,
        bool $isFinished = false
    ): ProviderTransaction {
        // 检查是否已存在
        $existing = $this->providerTransactionService->findByProviderAndTxid($provider, $txid);
        if ($existing) {
            throw new DuplicateTransactionException('Duplicate payout transaction');
        }

        return DB::transaction(function () use ($provider, $gameId, $userId, $txid, $roundId, $amount, $currency, $detail, $isFinished) {
            // 创建 provider transaction 记录
            $providerTransaction = $this->providerTransactionService->create(
                $provider,
                $gameId,
                $userId,
                $txid,
                $roundId,
                $detail
            );

            // 处理余额增加
            $this->balanceService->payout($userId, $amount, $currency, $gameId, $txid);

            // 更新订单
            $game = Game::find($gameId);
            if (!$game) {
                throw new GameNotFoundException('Game not found');
            }
            $order = $this->orderService->payout($userId, $amount, $game, $roundId, $isFinished);

            // 如果有订单，更新 provider transaction 的 order_id
            if ($order) {
                $this->providerTransactionService->updateOrderId($providerTransaction, $order->id);
            }

            return $providerTransaction;
        });
    }

    /**
     * 处理 refund 回调
     *
     * @param string $provider 提供商标识
     * @param int $gameId 游戏ID
     * @param int $userId 用户ID
     * @param string $txid 交易ID
     * @param string $roundId 回合ID
     * @param float $amount 金额
     * @param string $currency 货币类型
     * @param array $detail 详细信息
     * @return ProviderTransaction
     * @throws DuplicateTransactionException
     * @throws GameNotFoundException
     */
    public function handleRefund(
        string $provider,
        int $gameId,
        int $userId,
        string $txid,
        string $roundId,
        float $amount,
        string $currency,
        array $detail
    ): ProviderTransaction {
        // 检查是否已存在
        $existing = $this->providerTransactionService->findByProviderAndTxid($provider, $txid);
        if ($existing) {
            throw new DuplicateTransactionException('Duplicate refund transaction');
        }

        return DB::transaction(function () use ($provider, $gameId, $userId, $txid, $roundId, $amount, $currency, $detail) {
            // 创建 provider transaction 记录
            $providerTransaction = $this->providerTransactionService->create(
                $provider,
                $gameId,
                $userId,
                $txid,
                $roundId,
                $detail
            );

            // 处理余额退款
            $this->balanceService->refund($userId, $amount, $currency, $gameId, $txid);

            // 更新订单
            $game = Game::find($gameId);
            if (!$game) {
                throw new GameNotFoundException('Game not found');
            }
            $order = $this->orderService->refund($userId, $game, $roundId);

            // 如果有订单，更新 provider transaction 的 order_id
            if ($order) {
                $this->providerTransactionService->updateOrderId($providerTransaction, $order->id);
            }

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

    public function getProviderTransactionById(string $provider, string $txid): ProviderTransaction
    {
        return $this->providerTransactionService->findByProviderAndTxid($provider, $txid);
    }
}

