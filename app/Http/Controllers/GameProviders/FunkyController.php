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
use Illuminate\Support\Facades\DB;
use App\Exceptions\OrderNotFoundException;
use Illuminate\Support\Facades\Log;
class FunkyController extends Controller
{
    
    protected $providerCallbackService;
    protected $gameService;
    public function __construct()
    {
        $this->providerCallbackService = new ProviderCallbackService();
        $this->gameService = new GameService();
    }

    /**
     * 验证 Funky 请求头
     */
    protected function checkFunkyHeader(Request $request): void
    {
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
        try {
            $this->checkFunkyHeader($request);
            
            $token = $request->get('sessionId'); 
            $balance = $this->providerCallbackService->getBalance($token);
    
            return $this->success(['balance' => $balance]);
        } catch (InvalidTokenException $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_AUTH);
        } catch (\Exception $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
        }
    }

    /**
     * 检查下注
     * POST /gp/funky/Funky/Bet/CheckBet
     */
    public function checkBet(Request $request)
    {
        try {
            $this->checkFunkyHeader($request);
            
            $id = $request->get('id');
            $transaction = $this->providerCallbackService->getProviderTransactionById(GameProviderEnum::FUNKY->value, $id);
            $order = $transaction->order;
    
            return $this->success([
                'stake'         => $order->amount,
                'winAmount'     => $order->payout,
                'status'        => FunkyProvider::getStatus($order->amount, $order->payout),
                'statementDate' => $order->finished_at->format('Y-m-d H:i:s'),
            ]);
        } catch (ProviderTransactionNotFoundException $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_BET_404);
        } catch (\Exception $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
        }
    }

    /**
     * 下注
     * POST /gp/funky/Funky/Bet/PlaceBet
     */
    public function bet(Request $request): JsonResponse
    {
        try {
            $this->checkFunkyHeader($request);
        } catch (\Exception $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
        }
        
        $token = $request->get('sessionId');
        $detail = $request->get('bet');
        $txid = $request->header('X-Request-ID');

        $gameOutId = $detail['gameCode'];
        $roundId = $detail['refNo'];
        $amount = $detail['stake'];
        
        DB::beginTransaction();
        try {
            $result = $this->providerCallbackService->handleBet(
                GameProviderEnum::FUNKY->value,
                $gameOutId,
                $token,
                $txid,
                $roundId,
                $amount,
                $request->all(),
            );

            DB::commit();
            return $this->success([
                'balance' => $result['balance'],
            ]);
        } catch (InsufficientBalanceException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_BALANCE);
        } catch (DuplicateTransactionException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_BET_DUP);
        } catch (GameNotFoundException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_GAME_403);
        } catch (GameNotEnabledException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_GAME_403);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('FunkyController bet error', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
        }
    }

    /**
     * 结算下注
     * POST /gp/funky/Funky/Bet/SettleBet
     */
    public function settle(Request $request): JsonResponse
    {
        try {
            $this->checkFunkyHeader($request);
        } catch (\Exception $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
        }
        
        $detail = $request->get('betResultReq');
        $txid = $request->header('X-Request-ID');
        $roundId = $request->get('refNo');
        $amount = $detail['winAmount'];
        
        DB::beginTransaction();
        try {
            $result = $this->providerCallbackService->handlePayout(
                GameProviderEnum::FUNKY->value,
                $txid,
                $roundId,
                $amount,
                $request->all(),
            );
            DB::commit();
        } catch (DuplicateTransactionException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_BET_DUP);
        } catch (OrderNotFoundException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_BET_404);
        } catch (GameNotFoundException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_GAME_403);
        } catch (GameNotEnabledException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_GAME_403);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('FunkyController settle error', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
        }
        return $this->success([
            'refNo'         => $roundId,
            'balance'       => $result['balance'],
            'player_id' => $detail['playerId'],
            'currency' => $result['order']->currency,
            'statementDate' => $result['order']->finished_at,
        ]);
    }

    /**
     * 取消下注
     * POST /gp/funky/Funky/Bet/CancelBet
     */
    public function cancel(Request $request): JsonResponse
    {
        try {
            $this->checkFunkyHeader($request);
        } catch (\Exception $e) {
            return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
        }
        
        $txid = $request->header('X-Request-ID');
        $roundId = $request->get('refNo');

        DB::beginTransaction();
        try {
            $this->providerCallbackService->handleRefund(
                GameProviderEnum::FUNKY->value,
                $txid,
                $roundId,
                0,
                $request->all(),
            );
            DB::commit();
        } catch (DuplicateTransactionException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_BET_DUP);
        } catch (OrderNotFoundException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_BET_404);
        } catch (GameNotFoundException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_GAME_403);
        } catch (GameNotEnabledException $e) {
            DB::rollBack();
            return FunkyProvider::errorResp(FunkyProvider::ERR_GAME_403);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('FunkyController cancel error', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            return FunkyProvider::errorResp(FunkyProvider::ERR_SERVER_ERROR);
        }

        return $this->success([
            'refNo'         => $roundId,
        ]);
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
