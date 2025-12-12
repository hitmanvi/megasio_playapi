<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\Kyc;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KycController extends Controller
{
    /**
     * 获取当前用户的 KYC 信息
     */
    public function show(Request $request): JsonResponse
    {
        $kyc = Kyc::where('user_id', $request->user()->id)->first();

        if (!$kyc) {
            return $this->responseItem(null);
        }

        return $this->responseItem($kyc);
    }

    /**
     * 提交或更新 KYC 基本信息（初审）
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'birthdate' => 'nullable|string|max:20',
            'document_front' => 'nullable|string|url|max:500',
            'document_back' => 'nullable|string|url|max:500',
            'document_number' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        // 查找或创建 KYC 记录
        $kyc = Kyc::firstOrNew(['user_id' => $user->id]);

        // 更新字段
        $kyc->fill([
            'user_id' => $user->id,
            'name' => $request->input('name', $kyc->name),
            'birthdate' => $request->input('birthdate', $kyc->birthdate),
            'document_front' => $request->input('document_front', $kyc->document_front),
            'document_back' => $request->input('document_back', $kyc->document_back),
            'document_number' => $request->input('document_number', $kyc->document_number),
        ]);

        // 如果是更新且之前被拒绝，重置为待审核状态
        if ($kyc->exists && $kyc->isRejected()) {
            $kyc->status = Kyc::STATUS_PENDING;
            $kyc->reject_reason = null;
        }

        $kyc->save();

        return $this->responseItem($kyc);
    }

    /**
     * 提交高级认证（初审通过后）
     */
    public function submitAdvanced(Request $request): JsonResponse
    {
        $request->validate([
            'selfie' => 'required|string|url|max:500',
        ]);

        $user = $request->user();
        $kyc = Kyc::where('user_id', $user->id)->first();

        if (!$kyc) {
            return $this->error(ErrorCode::NOT_FOUND, 'KYC not found, please submit basic info first');
        }

        if (!$kyc->canSubmitAdvanced()) {
            return $this->error(ErrorCode::OPERATION_NOT_ALLOWED, 'Cannot submit advanced verification at current status');
        }

        $kyc->selfie = $request->input('selfie');
        $kyc->status = Kyc::STATUS_ADVANCED_PENDING;
        $kyc->reject_reason = null;
        $kyc->save();

        return $this->responseItem($kyc);
    }
}

