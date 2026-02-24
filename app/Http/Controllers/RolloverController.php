<?php

namespace App\Http\Controllers;

use App\Models\Rollover;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RolloverController extends Controller
{
    /**
     * 获取用户的 rollover 列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // 构建查询
        $query = Rollover::where('user_id', $user->id)
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

        // 按来源类型过滤
        if ($request->has('source_type')) {
            $sourceType = $request->input('source_type');
            if (is_array($sourceType)) {
                $query->whereIn('source_type', $sourceType);
            } else {
                $query->where('source_type', $sourceType);
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
     * 获取 rollover 类型列表
     */
    public function types(): JsonResponse
    {
        return $this->responseItem([Rollover::SOURCE_TYPE_DEPOSIT]);
    }

    /**
     * 获取 rollover 详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $rollover = Rollover::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$rollover) {
            return $this->error(\App\Enums\ErrorCode::NOT_FOUND, 'Rollover not found');
        }

        return $this->responseItem($this->formatRolloverForResponse($rollover));
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
            'source_type' => $rollover->source_type,
            'related_id' => $rollover->related_id,
            'currency' => $rollover->currency,
            'amount' => (float) $rollover->amount,
            'required_wager' => (float) $rollover->required_wager,
            'current_wager' => (float) $rollover->current_wager,
            'status' => $rollover->status,
            'progress_percent' => $rollover->getProgressPercent(),
            'completed_at' => $rollover->completed_at ? $rollover->completed_at->format('Y-m-d H:i:s') : null,
            'created_at' => $rollover->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $rollover->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
