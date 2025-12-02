<?php

namespace App\Services;

use App\Models\ProviderTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProviderTransactionService
{
    /**
     * 创建 Provider Transaction 记录
     *
     * @param string $provider 提供商标识
     * @param int $gameId 游戏ID
     * @param int $userId 用户ID
     * @param string $txid 交易ID
     * @param string $roundId 回合ID
     * @param array|null $detail 详细信息
     * @param int|null $orderId 订单ID
     * @return ProviderTransaction
     */
    public function create(
        string $provider,
        int $gameId,
        int $userId,
        string $txid,
        string $roundId,
        ?array $detail = null,
        ?int $orderId = null
    ): ProviderTransaction {
        return ProviderTransaction::create([
            'provider' => $provider,
            'game_id' => $gameId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'txid' => $txid,
            'round_id' => $roundId,
            'detail' => $detail,
        ]);
    }

    /**
     * 根据 provider 和 txid 查找 Transaction
     *
     * @param string $provider 提供商标识
     * @param string $txid 交易ID
     * @return ProviderTransaction|null
     */
    public function findByProviderAndTxid(string $provider, string $txid): ?ProviderTransaction
    {
        return ProviderTransaction::byProvider($provider)
            ->byTxid($txid)
            ->first();
    }

    /**
     * 根据 round_id 查找所有 Transaction
     *
     * @param string $roundId 回合ID
     * @return Collection
     */
    public function findByRoundId(string $roundId): Collection
    {
        return ProviderTransaction::byRoundId($roundId)->get();
    }

    /**
     * 根据 provider 和 round_id 查找所有 Transaction
     *
     * @param string $provider 提供商标识
     * @param string $roundId 回合ID
     * @return Collection
     */
    public function findByProviderAndRoundId(string $provider, string $roundId): Collection
    {
        return ProviderTransaction::byProvider($provider)
            ->byRoundId($roundId)
            ->get();
    }

    /**
     * 获取用户的 Provider Transactions（分页）
     *
     * @param int $userId 用户ID
     * @param array $filters 筛选条件（provider, game_id, order_id）
     * @param int $perPage 每页数量
     * @return LengthAwarePaginator
     */
    public function getUserTransactionsPaginated(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ProviderTransaction::byUser($userId);

        if (!empty($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        if (!empty($filters['game_id'])) {
            $query->byGame($filters['game_id']);
        }

        if (!empty($filters['order_id'])) {
            $query->byOrder($filters['order_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 获取游戏的 Provider Transactions（分页）
     *
     * @param int $gameId 游戏ID
     * @param array $filters 筛选条件（provider, user_id）
     * @param int $perPage 每页数量
     * @return LengthAwarePaginator
     */
    public function getGameTransactionsPaginated(int $gameId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ProviderTransaction::byGame($gameId);

        if (!empty($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        if (!empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 获取指定 provider 的 Transactions（分页）
     *
     * @param string $provider 提供商标识
     * @param array $filters 筛选条件（user_id, game_id）
     * @param int $perPage 每页数量
     * @return LengthAwarePaginator
     */
    public function getProviderTransactionsPaginated(string $provider, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ProviderTransaction::byProvider($provider);

        if (!empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (!empty($filters['game_id'])) {
            $query->byGame($filters['game_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 检查 Transaction 是否已存在
     *
     * @param string $provider 提供商标识
     * @param string $txid 交易ID
     * @return bool
     */
    public function exists(string $provider, string $txid): bool
    {
        return ProviderTransaction::byProvider($provider)
            ->byTxid($txid)
            ->exists();
    }

    /**
     * 更新 Transaction 的 detail
     *
     * @param ProviderTransaction $transaction
     * @param array $detail
     * @return bool
     */
    public function updateDetail(ProviderTransaction $transaction, array $detail): bool
    {
        return $transaction->update(['detail' => $detail]);
    }

    /**
     * 更新 Transaction 的 order_id
     *
     * @param ProviderTransaction $transaction
     * @param int $orderId
     * @return bool
     */
    public function updateOrderId(ProviderTransaction $transaction, int $orderId): bool
    {
        return $transaction->update(['order_id' => $orderId]);
    }
}

