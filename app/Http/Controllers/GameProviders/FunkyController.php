<?php

namespace App\Http\Controllers\GameProviders;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ProviderCallbackService;
use App\Enums\GameProvider;
use App\GameProviders\FunkyProvider;
use App\Exceptions\ProviderTransactionNotFoundException;
use App\Exceptions\InvalidTokenException;

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
        try {
            $token = $request->get('sessionId'); 
            $balance = $this->providerCallbackService->getBalance($token);
    
            return $this->success(['balance' => $balance]);
        } catch (InvalidTokenException $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_AUTH);
        }
    }

    /**
     * 检查下注
     * POST /gp/funky/Funky/Bet/CheckBet
     */
    public function checkBet(Request $request)
    {
        try {
            $id = $request->get('id');
            $transaction = $this->providerCallbackService->getProviderTransactionById(GameProvider::FUNKY->value, $id);
            $order = $transaction->order;
    
            return $this->success([
                'stake'         => $order->amount,
                'winAmount'     => $order->payout,
                'status'        => FunkyProvider::getStatus($order->amount, $order->payout),
                'statementDate' => $order->finished_at->format('Y-m-d H:i:s'),
            ]);
        } catch (ProviderTransactionNotFoundException $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_BET_404);
        }
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

    private function success($data)
    {
        return response()->json([
            'errorCode' => 0,
            'errorMessage' => 'Success',
            'data'      => $data,
        ]);
    }
}
