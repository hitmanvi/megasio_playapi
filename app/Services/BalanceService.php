<?php

namespace App\Services;

use App\Events\BalanceChanged;
use App\Exceptions\Exception;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Balance;
use App\Models\Transaction;
use App\Enums\ErrorCode;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    protected $transactionService;

    public function __construct()
    {
        $this->transactionService = new TransactionService();
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
            $balance = Balance::create([
                'user_id' => $userId,
                'currency' => $currency,
                'available' => 0,
                'frozen' => 0,
                'version' => 0,
            ]);
        }

        // Update existing balance with optimistic locking
        $newAvailable = $balance->available;
        $newFrozen = $balance->frozen;
        
        if ($type === 'available') {
            if ($operation === 'subtract' && $balance->available < $amount) {
                throw new InsufficientBalanceException();
            }
            $newAvailable = $operation === 'add' 
                ? $balance->available + $amount 
                : $balance->available - $amount;
        } else {
            if ($operation === 'subtract' && $balance->frozen < $amount) {
                throw new InsufficientBalanceException();
            }
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
            throw new Exception(ErrorCode::BALANCE_UPDATE_FAILED);
        }

        // 重新加载余额
        $updatedBalance = $balance->fresh();
        
        // 触发余额变动事件（传递 user_id，查询延迟到监听器）
        event(new BalanceChanged($userId, $updatedBalance, $amount, $operation, $type));

        return $updatedBalance;
    }

    /**
     * Increment available balance.
     */
    public function increment(int $userId, string $currency, float $amount, string $type = 'available'): Balance
    {
        return $this->updateBalance($userId, $currency, $amount, 'add', $type);
    }

    /**
     * Decrement available balance.
     */
    public function decrement(int $userId, string $currency, float $amount, string $type = 'available'): Balance
    {
        return $this->updateBalance($userId, $currency, $amount, 'subtract', $type);
    }

    /**
     * Freeze amount from available balance.
     */
    public function freezeAmount(int $userId, string $currency, float $amount): bool
    {
        $balance = $this->getBalance($userId, $currency);
        
        if (!$balance || $balance->available < $amount) {
            throw new InsufficientBalanceException();
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
            throw new InsufficientBalanceException();
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
                (float)$balance->available,
                Transaction::TYPE_DEPOSIT,
                $relatedEntityId,
                $notes
            );

            return [
                'balance' => $balance,
                'transaction' => $transaction,
            ];
        });
    }

    public function finishWithdraw(int $userId, string $currency, float $amount, string $notes, int $relatedEntityId): array
    {
        return DB::transaction(function () use ($userId, $currency, $amount, $notes, $relatedEntityId) {
            $balance = $this->updateBalance($userId, $currency, $amount, 'subtract', 'frozen');
            $transaction = $this->transactionService->createTransaction($userId, $currency, -$amount, (float)$balance->available, Transaction::TYPE_WITHDRAWAL_UNFREEZE, $relatedEntityId, $notes);
            return [
                'balance' => $balance,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Request withdraw - freeze amount from available balance.
     * This is called when a withdraw order is created.
     */
    public function requestWithdraw(int $userId, string $currency, float $amount, string $notes, int $relatedEntityId): array
    {
        return DB::transaction(function () use ($userId, $currency, $amount, $notes, $relatedEntityId) {
            // Check if user has sufficient available balance
            if (!$this->hasSufficientAvailableBalance($userId, $currency, $amount)) {
                throw new InsufficientBalanceException();
            }

            // Freeze amount from available balance
            $this->freezeAmount($userId, $currency, $amount);
            $balance = $this->getBalance($userId, $currency);
            
            // Create transaction record
            $transaction = $this->transactionService->createTransaction(
                $userId,
                $currency,
                -$amount,
                (float)$balance->available,
                Transaction::TYPE_WITHDRAWAL,
                $relatedEntityId,
                $notes
            );

            return [
                'balance' => $balance,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Get user's all balances.
     * If user has set display currencies preference, only return those currencies.
     */
    public function getUserBalances(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        $query = Balance::where('user_id', $userId);
        
        // 检查用户是否设置了展示货币偏好
        // $user = \App\Models\User::find($userId);
        // if ($user) {
        //     $displayCurrencies = $user->getDisplayCurrencies();
        //     if (!empty($displayCurrencies) && is_array($displayCurrencies)) {
        //         // 只返回用户选择的货币
        //         $query->whereIn('currency', $displayCurrencies);
        //     }
        // }
        
        return $query->get();
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

    public function bet($userId, $amount, $currency,$gameId, $txid)
    {
        return DB::transaction(function () use ($userId, $amount, $currency, $gameId, $txid) {
            $balance = $this->updateBalance($userId, $currency, $amount, 'subtract', 'available');
            $entityId = $gameId."_".$txid;
            $transaction = $this->transactionService->createTransaction($userId, $currency, -$amount, (float)$balance->available, Transaction::TYPE_BET, $entityId);
            return [
                'balance' => $balance,
                'transaction' => $transaction,
            ];
        });
    }

    public function payout($userId, $amount, $currency,$gameId, $txid)
    {
        return DB::transaction(function () use ($userId, $amount, $currency, $gameId, $txid) {
            $balance = $this->updateBalance($userId, $currency, $amount, 'add', 'available');
            $entityId = $gameId."_".$txid;
            $transaction = $this->transactionService->createTransaction($userId, $currency, $amount, (float)$balance->available, Transaction::TYPE_PAYOUT, $entityId);
            return [
                'balance' => $balance,
                'transaction' => $transaction,
            ];
        });
    }

    public function refund($userId, $amount, $currency,$gameId, $txid)
    {
        return DB::transaction(function () use ($userId, $amount, $currency, $gameId, $txid) {
            $balance = $this->updateBalance($userId, $currency, $amount, 'add', 'available');
            $entityId = $gameId."_".$txid;
            $transaction = $this->transactionService->createTransaction($userId, $currency, $amount, (float)$balance->available, Transaction::TYPE_REFUND, $entityId);
            return [
                'balance' => $balance,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * 签到奖励
     */
    public function checkInReward(int $userId, string $currency, float $amount, int $checkInId): array
    {
        return DB::transaction(function () use ($userId, $currency, $amount, $checkInId) {
            $balance = $this->updateBalance($userId, $currency, $amount, 'add', 'available');
            $transaction = $this->transactionService->createTransaction(
                $userId,
                $currency,
                $amount,
                (float)$balance->available,
                Transaction::TYPE_CHECK_IN_REWARD,
                $checkInId,
                'Check-in reward'
            );
            return [
                'balance' => $balance,
                'transaction' => $transaction,
            ];
        });
    }
}
