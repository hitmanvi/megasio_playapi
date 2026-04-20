<?php

namespace App\Http\Controllers;

use App\Exceptions\Exception as AppException;
use App\Services\BonusTaskService;
use App\Services\PromotionCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionCodeController extends Controller
{
    protected PromotionCodeService $promotionCodeService;

    protected BonusTaskService $bonusTaskService;

    public function __construct()
    {
        $this->promotionCodeService = new PromotionCodeService;
        $this->bonusTaskService = new BonusTaskService;
    }

    /**
     * 领取兑换码（生成 BonusTask 等，依 bonus_type）
     */
    public function claim(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:255',
        ]);

        try {
            $result = $this->promotionCodeService->claim((int) $request->user()->id, (string) $request->input('code'));

            return $this->responseItem([
                'claim' => [
                    'id' => $result['claim']->id,
                    'status' => $result['claim']->status,
                    'claimed_at' => $result['claim']->claimed_at?->format('Y-m-d H:i:s'),
                ],
                'bonus_task' => $this->bonusTaskService->formatBonusTask($result['bonus_task']),
            ]);
        } catch (AppException $e) {
            return $this->error($e->getErrorCode(), $e->getMessage());
        }
    }
}
