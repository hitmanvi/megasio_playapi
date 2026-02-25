<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Exceptions\Exception as AppException;
use App\Models\WeeklyCashback;
use App\Services\WeeklyCashbackService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WeeklyCashbackController extends Controller
{
    protected WeeklyCashbackService $weeklyCashbackService;

    public function __construct(WeeklyCashbackService $weeklyCashbackService)
    {
        $this->weeklyCashbackService = $weeklyCashbackService;
    }

    /**
     * 获取上周可领取的 cashback（单个），无记录返回空；同时将非上周的 claimable 标记为过期
     */
    public function claimable(Request $request): JsonResponse
    {
        $user = $request->user();
        $cashback = $this->weeklyCashbackService->getClaimableForUser($user->id);
        return $this->responseItem($cashback ? $this->formatItem($cashback) : null);
    }

    /**
     * 获取 weekly cashback 详情（通过 no）
     */
    public function show(Request $request, string $no): JsonResponse
    {
        $user = $request->user();

        $cashback = WeeklyCashback::where('user_id', $user->id)->where('no', $no)->first();

        if (!$cashback) {
            return $this->error(ErrorCode::WEEKLY_CASHBACK_NOT_FOUND);
        }

        return $this->responseItem($this->formatItem($cashback));
    }

    /**
     * 领取 weekly cashback（通过 no）
     */
    public function claim(Request $request, string $no): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->weeklyCashbackService->claim($user->id, $no);
            $data = $this->formatItem($result['cashback']);
            $data['claim_amount'] = $result['claim_amount'];
            $data['currency'] = $result['currency'];
            return $this->responseItem($data);
        } catch (AppException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        }
    }

    protected function formatItem(WeeklyCashback $cashback): array
    {
        return [
            'no' => $cashback->no,
            'period' => $cashback->period,
            'currency' => $cashback->currency,
            'wager' => (float) $cashback->wager,
            'payout' => (float) $cashback->payout,
            'status' => $cashback->status,
            'rate' => (float) $cashback->rate,
            'amount' => (float) $cashback->amount,
            'claimed_at' => $cashback->claimed_at?->format('Y-m-d H:i:s'),
            'created_at' => $cashback->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $cashback->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
