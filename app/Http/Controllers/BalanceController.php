<?php

namespace App\Http\Controllers;

use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BalanceController extends Controller
{
    protected BalanceService $balanceService;

    public function __construct(BalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    /**
     * 获取用户余额列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $balances = $this->balanceService->getUserBalances($user->id);

        // 为每个余额添加可提现金额
        $balancesArray = $balances->map(function ($balance) use ($user) {
            $balanceArray = $balance->toArray();
            $balanceArray['withdrawable'] = $this->balanceService->getWithdrawableAmount($user->id, $balance->currency);
            return $balanceArray;
        })->toArray();

        return $this->responseList($balancesArray);
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
                'withdrawable' => 0,
            ]);
        }

        return $this->responseItem([
            'available' => $balance->available,
            'frozen' => $balance->frozen,
            'currency' => $balance->currency,
            'withdrawable' => $this->balanceService->getWithdrawableAmount($user->id, $currency),
        ]);
    }
}
