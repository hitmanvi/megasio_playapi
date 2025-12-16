<?php

namespace App\Http\Controllers\GameProviders;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ProviderCallbackService;
use App\Enums\GameProvider as GameProviderEnum;
use App\GameProviders\FunkyProvider;
use App\Exceptions\ProviderTransactionNotFoundException;
use App\Exceptions\InvalidTokenException;
use App\Services\GameService;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\GameNotFoundException;
use App\Exceptions\GameNotEnabledException;
use App\Exceptions\OrderNotFoundException;
use Illuminate\Support\Facades\Log;

class FunkyController extends Controller
{
    protected ProviderCallbackService $providerCallbackService;
    protected GameService $gameService;

    /**
     * 异常与错误码映射
     */
    protected array $exceptionMap = [
        InvalidTokenException::class => FunkyProvider::ERR_AUTH,
        InsufficientBalanceException::class => FunkyProvider::ERR_BALANCE,
        DuplicateTransactionException::class => FunkyProvider::ERR_BET_DUP,
        GameNotFoundException::class => FunkyProvider::ERR_GAME_403,
        GameNotEnabledException::class => FunkyProvider::ERR_GAME_403,
        OrderNotFoundException::class => FunkyProvider::ERR_BET_404,
        ProviderTransactionNotFoundException::class => FunkyProvider::ERR_BET_404,
    ];

    public function __construct()
    {
        $this->providerCallbackService = new ProviderCallbackService();
        $this->gameService = new GameService();
    }

    /**
     * 统一异常处理
     */
    protected function handleException(\Throwable $e, string $action = ''): JsonResponse
    {
        foreach ($this->exceptionMap as $exceptionClass => $errorCode) {
            if ($e instanceof $exceptionClass) {
                return FunkyProvider::errorResp($errorCode);
            }
        }

        // 未知异常记录日志
        Log::error('FunkyController error', [
            'action' => $action,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
    }

    /**
     * 执行回调操作并统一处理异常
     */
    protected function executeCallback(callable $callback, string $action): JsonResponse
    {
        try {
            $this->checkFunkyHeader(request());
            return $callback();
        } catch (\Throwable $e) {
            return $this->handleException($e, $action);
        }
    }

    /**
     * 验证 Funky 请求头
     */
    protected function checkFunkyHeader(Request $request): void
    {
        return;
        // 获取配置中的 funkyId 和 funkySecret
        $funkyId = config('providers.funky.funky_id');
        $funkySecret = config('providers.funky.funky_secret');
        
        $userAgent = $request->header('User-Agent');
        $authentication = $request->header('Authentication');
        
        if ($userAgent !== $funkyId || $authentication !== $funkySecret) {
            throw new \Exception('Invalid Funky headers');
        }
    }

    /**
     * 获取用户余额
     * POST /gp/funky/Funky/User/GetBalance
     */
    public function getBalance(Request $request): JsonResponse
    {
        return $this->executeCallback(function () use ($request) {
            $token = $request->get('sessionId');
            $balance = $this->providerCallbackService->getBalance($token);

            return $this->success(['balance' => $balance]);
        }, 'getBalance');
    }

    /**
     * 检查下注
     * POST /gp/funky/Funky/Bet/CheckBet
     */
    public function checkBet(Request $request): JsonResponse
    {
        return $this->executeCallback(function () use ($request) {
            $id = $request->get('id');
            $transaction = $this->providerCallbackService->getProviderTransactionById(GameProviderEnum::FUNKY->value, $id);
            $order = $transaction->order;

            return $this->success([
                'stake'         => $order->amount,
                'winAmount'     => $order->payout,
                'status'        => FunkyProvider::getStatus($order->amount, $order->payout),
                'statementDate' => $order->finished_at->format('Y-m-d H:i:s'),
            ]);
        }, 'checkBet');
    }

    /**
     * 下注
     * POST /gp/funky/Funky/Bet/PlaceBet
     */
    public function bet(Request $request): JsonResponse
    {
        return $this->executeCallback(function () use ($request) {
            $token = $request->get('sessionId');
            $detail = $request->get('bet');
            $txid = $request->header('X-Request-ID');

            $result = $this->providerCallbackService->handleBet(
                GameProviderEnum::FUNKY->value,
                $detail['gameCode'],
                $token,
                $txid,
                $detail['refNo'],
                $detail['stake'],
                $request->all(),
            );

            return $this->success([
                'balance' => $result['balance'],
            ]);
        }, 'bet');
    }

    /**
     * 结算下注
     * POST /gp/funky/Funky/Bet/SettleBet
     */
    public function settle(Request $request): JsonResponse
    {
        return $this->executeCallback(function () use ($request) {
            $detail = $request->get('betResultReq');
            $txid = $request->header('X-Request-ID');
            $roundId = $request->get('refNo');

            $result = $this->providerCallbackService->handlePayout(
                GameProviderEnum::FUNKY->value,
                $txid,
                $roundId,
                $detail['winAmount'],
                $request->all(),
            );

            return $this->success([
                'refNo'         => $roundId,
                'balance'       => $result['balance'],
                'player_id'     => $detail['playerId'],
                'currency'      => $result['order']->currency,
                'statementDate' => $result['order']->finished_at,
            ]);
        }, 'settle');
    }

    /**
     * 取消下注
     * POST /gp/funky/Funky/Bet/CancelBet
     */
    public function cancel(Request $request): JsonResponse
    {
        return $this->executeCallback(function () use ($request) {
            $txid = $request->header('X-Request-ID');
            $roundId = $request->get('refNo');

            $this->providerCallbackService->handleRefund(
                GameProviderEnum::FUNKY->value,
                $txid,
                $roundId,
                0,
                $request->all(),
            );

            return $this->success([
                'refNo' => $roundId,
            ]);
        }, 'cancel');
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
