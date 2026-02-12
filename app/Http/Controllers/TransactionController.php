<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\ErrorCode;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * 获取支持的交易类型
     */
    public function types(): JsonResponse
    {
        $types = [
            Transaction::TYPE_DEPOSIT,
            Transaction::TYPE_WITHDRAWAL,
            Transaction::TYPE_WITHDRAWAL_UNFREEZE,
            Transaction::TYPE_REFUND,
            Transaction::TYPE_BET,
            Transaction::TYPE_PAYOUT,
            Transaction::TYPE_CHECK_IN_REWARD,
            Transaction::TYPE_BONUS_TASK_REWARD,
            Transaction::TYPE_INVITATION_REWARD,
        ];

        return $this->responseItem($types);
    }

    /**
     * 获取交易记录列表
     * 
     * 支持的时间范围参数：24h, 7d, 30d
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        // 验证时间范围参数
        $period = $request->input('period');
        $allowedPeriods = ['24h', '7d', '30d'];
        if ($period && !in_array($period, $allowedPeriods)) {
            return $this->error(ErrorCode::VALIDATION_ERROR, 'Period must be one of: 24h, 7d, 30d');
        }

        // 构建筛选条件
        $filters = [];
        if ($request->has('currency')) {
            $filters['currency'] = $request->input('currency');
        }
        if ($request->has('type')) {
            $filters['type'] = $request->input('type');
        }
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }
        if ($period) {
            $filters['period'] = $period;
        }

        $perPage = max(1, (int)$request->input('per_page', 20));
        $transactions = $this->transactionService->getUserTransactionsPaginated($user->id, $filters, $perPage);

        // 格式化返回数据
        $transactions->getCollection()->transform(function ($transaction) {
            return $this->transactionService->formatTransactionForResponse($transaction, false);
        });

        return $this->responseListWithPaginator($transactions);
    }

    /**
     * 获取交易详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error(ErrorCode::UNAUTHORIZED, 'User not authenticated');
        }

        $transaction = $this->transactionService->getTransactionById($id);

        if (!$transaction) {
            return $this->error(ErrorCode::NOT_FOUND, 'Transaction not found');
        }

        // 确保交易属于当前用户
        if ($transaction->user_id !== $user->id) {
            return $this->error(ErrorCode::FORBIDDEN, 'Access denied');
        }

        return $this->responseItem(
            $this->transactionService->formatTransactionForResponse($transaction, true)
        );
    }
}

