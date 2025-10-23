<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
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
        string $notes = null,
        int $relatedEntityId = null
    ): Transaction {
        return Transaction::create([
            'user_id' => $userId,
            'currency' => $currency,
            'amount' => $amount,
            'type' => $type,
            'status' => Transaction::STATUS_COMPLETED, // 固定为完成状态
            'notes' => $notes,
            'related_entity_id' => $relatedEntityId,
            'transaction_time' => now(),
        ]);
    }

    /**
     * Get user transactions with filters.
     */
    public function getUserTransactions(
        int $userId,
        string $currency = null,
        string $type = null,
        Carbon $startDate = null,
        Carbon $endDate = null,
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
     * Get transaction by ID.
     */
    public function getTransactionById(int $id): ?Transaction
    {
        return Transaction::find($id);
    }
}
