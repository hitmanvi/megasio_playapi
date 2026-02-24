<?php

namespace App\Http\Controllers;

use App\Models\Rollover;
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
     * 获取用户的 rollover 列表
     */
    public function rollovers(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // 构建查询
        $query = Rollover::where('user_id', $user->id)
            ->with(['deposit'])
            ->orderBy('created_at', 'desc');

        // 按货币过滤
        if ($request->has('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        // 按状态过滤
        if ($request->has('status')) {
            $status = $request->input('status');
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        // 分页
        $perPage = max(1, (int) $request->input('per_page', 20));
        $rollovers = $query->paginate($perPage);

        // 格式化返回数据
        $rollovers->getCollection()->transform(function ($rollover) {
            return $this->formatRolloverForResponse($rollover);
        });

        return $this->responseListWithPaginator($rollovers);
    }

    /**
     * 格式化 rollover 数据用于 API 响应
     *
     * @param Rollover $rollover
     * @return array
     */
    protected function formatRolloverForResponse(Rollover $rollover): array
    {
        return [
            'id' => $rollover->id,
            'deposit_id' => $rollover->deposit_id,
            'currency' => $rollover->currency,
            'deposit_amount' => (float) $rollover->deposit_amount,
            'required_wager' => (float) $rollover->required_wager,
            'current_wager' => (float) $rollover->current_wager,
            'status' => $rollover->status,
            'progress_percent' => $rollover->getProgressPercent(),
            'completed_at' => $rollover->completed_at ? $rollover->completed_at->format('Y-m-d H:i:s') : null,
            'created_at' => $rollover->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $rollover->updated_at->format('Y-m-d H:i:s'),
            'deposit' => $rollover->deposit ? [
                'id' => $rollover->deposit->id,
                'order_no' => $rollover->deposit->order_no,
                'amount' => (float) $rollover->deposit->amount,
                'actual_amount' => $rollover->deposit->actual_amount ? (float) $rollover->deposit->actual_amount : null,
            ] : null,
        ];
    }

}
