<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    /**
     * Get balance for specific user and currency.
     */
    public function getBalance(int $userId, string $currency): ?Balance
    {
        return Balance::where('user_id', $userId)
                     ->where('currency', $currency)
                     ->first();
    }

    /**
     * Create or update balance with optimistic locking.
     */
    public function updateBalance(int $userId, string $currency, float $amount, string $operation = 'add', string $type = 'available'): Balance
    {
        $balance = $this->getBalance($userId, $currency);
        
        if (!$balance) {
            // Create new balance record
            $data = [
                'user_id' => $userId,
                'currency' => $currency,
                'available' => 0,
                'frozen' => 0,
                'version' => 0,
            ];
            
            if ($type === 'available') {
                $data['available'] = $operation === 'add' ? $amount : -$amount;
            } else {
                $data['frozen'] = $operation === 'add' ? $amount : -$amount;
            }
            
            return Balance::create($data);
        }

        // Update existing balance with optimistic locking
        $newAvailable = $balance->available;
        $newFrozen = $balance->frozen;
        
        if ($type === 'available') {
            $newAvailable = $operation === 'add' 
                ? $balance->available + $amount 
                : $balance->available - $amount;
        } else {
            $newFrozen = $operation === 'add' 
                ? $balance->frozen + $amount 
                : $balance->frozen - $amount;
        }

        $updated = Balance::where('id', $balance->id)
                         ->where('version', $balance->version)
                         ->update([
                             'available' => $newAvailable,
                             'frozen' => $newFrozen,
                             'version' => $balance->version + 1,
                         ]);

        if (!$updated) {
            throw new \Exception('Balance update failed due to concurrent modification');
        }

        return $balance->fresh();
    }

    /**
     * Freeze amount from available balance.
     */
    public function freezeAmount(int $userId, string $currency, float $amount): bool
    {
        $balance = $this->getBalance($userId, $currency);
        
        if (!$balance || $balance->available < $amount) {
            throw new \Exception('Insufficient available balance');
        }

        return $this->updateBalance($userId, $currency, $amount, 'subtract', 'available') &&
               $this->updateBalance($userId, $currency, $amount, 'add', 'frozen');
    }

    /**
     * Unfreeze amount from frozen balance.
     */
    public function unfreezeAmount(int $userId, string $currency, float $amount): bool
    {
        $balance = $this->getBalance($userId, $currency);
        
        if (!$balance || $balance->frozen < $amount) {
            throw new \Exception('Insufficient frozen balance');
        }

        return $this->updateBalance($userId, $currency, $amount, 'subtract', 'frozen') &&
               $this->updateBalance($userId, $currency, $amount, 'add', 'available');
    }

    /**
     * Deposit amount to available balance.
     */
    public function deposit(int $userId, string $currency, float $amount, string $notes, int $relatedEntityId): array
    {
        return DB::transaction(function () use ($userId, $currency, $amount, $notes, $relatedEntityId) {
            // Update balance
            $balance = $this->updateBalance($userId, $currency, $amount, 'add', 'available');
            
            // Create transaction record
            $transaction = $this->transactionService->createTransaction(
                $userId,
                $currency,
                $amount,
                Transaction::TYPE_DEPOSIT,
                $notes,
                $relatedEntityId
            );

            return [
                'balance' => $balance,
                'transaction' => $transaction,
            ];
        });
    }


    /**
     * Get user's all balances.
     */
    public function getUserBalances(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return Balance::where('user_id', $userId)->get();
    }

    /**
     * Check if user has sufficient available balance.
     */
    public function hasSufficientAvailableBalance(int $userId, string $currency, float $amount): bool
    {
        $balance = $this->getBalance($userId, $currency);
        return $balance && $balance->available >= $amount;
    }

    /**
     * Check if user has sufficient frozen balance.
     */
    public function hasSufficientFrozenBalance(int $userId, string $currency, float $amount): bool
    {
        $balance = $this->getBalance($userId, $currency);
        return $balance && $balance->frozen >= $amount;
    }

}
