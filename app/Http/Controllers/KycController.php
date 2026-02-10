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

        // 保存当前状态，用于检查是否需要保持状态
        $previousStatus = $kyc->exists ? $kyc->status : null;

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
        } elseif (!$kyc->exists) {
            // 新创建的记录，设置为待审核状态
            $kyc->status = Kyc::STATUS_PENDING;
        } elseif ($previousStatus === Kyc::STATUS_PENDING) {
            // 如果之前是待审核状态，保持待审核状态（不改变状态）
            $kyc->status = Kyc::STATUS_PENDING;
        }
        // 如果之前是 approved 或更高级的状态，保持原状态不变

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

        // 保存上传的材料
        $kyc->selfie = $request->input('selfie');

        // 检查上一级（初审）是否审核通过
        // 如果初审通过或高级认证被拒绝后重新提交，更新状态为 advanced_pending
        // 如果初审未通过，保持当前状态不变
        if ($kyc->isApproved() || $kyc->isAdvancedRejected()) {
            // 上一级已通过，可以提交高级认证，更新状态
            $kyc->status = Kyc::STATUS_ADVANCED_PENDING;
            $kyc->reject_reason = null;
        }
        // 如果上一级未通过（pending 或 rejected），保持当前状态不变

        $kyc->save();

        return $this->responseItem($kyc);
    }
}

