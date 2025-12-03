<?php

namespace App\Http\Controllers\GameProviders;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ProviderCallbackService;

class FunkyController extends Controller
{
    protected $funkyProvider;
    protected $providerCallbackService;

    public function __construct()
    {
        $this->providerCallbackService = new ProviderCallbackService();
    }

    /**
     * 获取用户余额
     * POST /gp/funky/Funky/User/GetBalance
     */
    public function getBalance(Request $request): JsonResponse
    {
        $token = $request->get('sessionId'); 
        $balance = $this->providerCallbackService->getBalance($token);

        return $this->resp(['balance' => $balance]);
    }

    /**
     * 检查下注
     * POST /gp/funky/Funky/Bet/CheckBet
     */
    public function checkBet(Request $request): JsonResponse
    {
        // TODO: 实现检查下注逻辑
        return response()->json([]);
    }

    /**
     * 下注
     * POST /gp/funky/Funky/Bet/PlaceBet
     */
    public function bet(Request $request): JsonResponse
    {
        // TODO: 实现下注逻辑
        return response()->json([]);
    }

    /**
     * 结算下注
     * POST /gp/funky/Funky/Bet/SettleBet
     */
    public function settle(Request $request): JsonResponse
    {
        // TODO: 实现结算逻辑
        return response()->json([]);
    }

    /**
     * 取消下注
     * POST /gp/funky/Funky/Bet/CancelBet
     */
    public function cancel(Request $request): JsonResponse
    {
        // TODO: 实现取消下注逻辑
        return response()->json([]);
    }

    private function resp($data)
    {
        return response()->json([
            'errorCode' => 0,
            'errorMessage' => 'Success',
            'data'      => $data,
        ]);
    }
}
