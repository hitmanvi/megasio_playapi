<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class TransactionService
{
    /**
     * Create a new transaction.
     */
    public function createTransaction(
        int $userId,
        string $currency,
        float $amount,
        string $type,
        string $relatedEntityId,
        string $notes = ''
    ): Transaction {
        return Transaction::create([
            'user_id' => $userId,
            'currency' => $currency,
            'amount' => $amount,
            'type' => $type,
            'status' => Transaction::STATUS_COMPLETED, // 固定为完成状态
            'related_entity_id' => $relatedEntityId,
            'notes' => $notes,
            'transaction_time' => now(),
        ]);
    }

    /**
     * Get user transactions with filters.
     */
    public function getUserTransactions(
        int $userId,
        ?string $currency,
        ?string $type,
        ?Carbon $startDate,
        ?Carbon $endDate,
        int $limit = 50,
        int $offset = 0
    ): Collection {
        $query = Transaction::forUser($userId);

        if ($currency) {
            $query->forCurrency($currency);
        }

        if ($type) {
            $query->ofType($type);
        }

        if ($startDate && $endDate) {
            $query->inDateRange($startDate, $endDate);
        }

        return $query->orderBy('transaction_time', 'desc')
                    ->limit($limit)
                    ->offset($offset)
                    ->get();
    }

    /**
     * Get user transactions with filters and pagination.
     *
     * @param int $userId
     * @param array $filters Supported filters: currency, type, status, period (24h, 7d, 30d)
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserTransactionsPaginated(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->where('user_id', $userId)
            ->with(['user'])
            ->orderBy('transaction_time', 'desc');

        // Apply filters
        if (isset($filters['currency'])) {
            $query->forCurrency($filters['currency']);
        }

        if (isset($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (isset($filters['status'])) {
            $query->withStatus($filters['status']);
        }

        // Apply time period filter (24h, 7d, 30d)
        if (isset($filters['period'])) {
            $endDate = Carbon::now();
            $startDate = match($filters['period']) {
                '24h' => $endDate->copy()->subDay(),
                '7d' => $endDate->copy()->subDays(7),
                '30d' => $endDate->copy()->subDays(30),
                default => null,
            };
            
            if ($startDate) {
                $query->inDateRange($startDate, $endDate);
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * Format transaction for API response.
     *
     * @param Transaction $transaction
     * @param bool $includeDetails Include additional details
     * @return array
     */
    public function formatTransactionForResponse(Transaction $transaction, bool $includeDetails = false): array
    {
        $data = [
            'id' => $transaction->id,
            'currency' => $transaction->currency,
            'amount' => (float)$transaction->amount,
            'type' => $transaction->type,
            'status' => $transaction->status,
            'transaction_time' => $transaction->transaction_time ? $transaction->transaction_time->format('Y-m-d H:i:s') : null,
            'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
        ];

        if ($transaction->notes) {
            $data['notes'] = $transaction->notes;
        }

        if ($transaction->related_entity_id) {
            $data['related_entity_id'] = $transaction->related_entity_id;
        }

        if ($includeDetails) {
            $data['updated_at'] = $transaction->updated_at->format('Y-m-d H:i:s');

            // 加载关联实体
            $entity = $transaction->getRelatedEntity();
            if ($entity) {
                $data['entity'] = $entity;
            }
        }

        return $data;
    }

    /**
     * Get transaction by ID.
     */
    public function getTransactionById(int $id): ?Transaction
    {
        return Transaction::find($id);
    }
}
