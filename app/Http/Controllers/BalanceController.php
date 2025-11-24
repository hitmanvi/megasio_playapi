<?php

namespace App\Http\Controllers;

use App\Services\BalanceService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BalanceController extends Controller
{
    protected BalanceService $balanceService;
    protected TransactionService $transactionService;

    public function __construct(BalanceService $balanceService, TransactionService $transactionService)
    {
        $this->balanceService = $balanceService;
        $this->transactionService = $transactionService;
    }

    /**
     * 获取用户余额列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $balances = $this->balanceService->getUserBalances($user->id);

        return $this->responseList($balances->toArray());
    }

    /**
     * 获取指定货币的余额
     */
    public function show(Request $request, string $currency): JsonResponse
    {
        $user = $request->user();
        $balance = $this->balanceService->getBalance($user->id, $currency);

        if (!$balance) {
            return $this->responseItem([
                'available' => 0,
                'frozen' => 0,
                'currency' => $currency,
            ]);
        }

        return $this->responseItem([
            'available' => $balance->available,
            'frozen' => $balance->frozen,
            'currency' => $balance->currency,
        ]);
    }

    /**
     * 获取用户的交易记录
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currency = $request->input('currency');
        $type = $request->input('type');
        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $transactions = $this->transactionService->getUserTransactions(
            $user->id,
            $currency,
            $type,
            null,
            null,
            $limit,
            $offset
        );

        return $this->responseList($transactions->toArray(), ['total' => $transactions->count()]);
    }

    /**
     * 设置用户选择的展示货币列表
     */
    public function setDisplayCurrencies(Request $request): JsonResponse
    {
        $request->validate([
            'currencies' => 'array',
            'currencies.*' => 'string|max:10',
        ]);

        $user = $request->user();
        $currencies = $request->input('currencies', []);

        // 验证货币代码是否有效（如果不为空）
        if (!empty($currencies)) {
            $validCurrencies = \App\Models\Currency::whereIn('code', $currencies)
                ->pluck('code')
                ->toArray();

            if (count($validCurrencies) !== count($currencies)) {
                return $this->error(\App\Enums\ErrorCode::VALIDATION_ERROR, [
                    'currencies' => ['Some currency codes are invalid'],
                ]);
            }
        } else {
            $validCurrencies = [];
        }

        // 设置用户选择的展示货币
        $user->setDisplayCurrencies($validCurrencies);

        return $this->responseItem([
            'currencies' => $validCurrencies,
        ]);
    }

    /**
     * 获取用户选择的展示货币列表
     */
    public function getDisplayCurrencies(Request $request): JsonResponse
    {
        $user = $request->user();
        $currencies = $user->getDisplayCurrencies();

        return $this->responseItem([
            'currencies' => $currencies,
        ]);
    }
}
