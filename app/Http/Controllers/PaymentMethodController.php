<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    /**
     * 获取支付方式列表
     * 
     * 支持根据currency和type进行筛选
     * currency和type必须同时提供
     */
    public function index(Request $request): JsonResponse
    {
        // 验证：currency和type必须同时存在
        if (!$request->has('currency') || !$request->has('type')) {
            return $this->error(\App\Enums\ErrorCode::VALIDATION_ERROR, [
                'currency' => ['Currency and type parameters are required'],
                'type' => ['Currency and type parameters are required'],
            ]);
        }

        $query = PaymentMethod::query();

        // 筛选：货币类型
        $query->byCurrency($request->input('currency'));

        // 筛选：支付类型 (deposit/withdraw)
        $query->byType($request->input('type'));

        // 默认只返回启用的支付方式，并按sort_id排序
        $query->enabled()->ordered();

        $paymentMethods = $query->get();

        $result = $paymentMethods->map(function ($method) {
            return [
                'id' => $method->id,
                'key' => $method->key,
                'icon' => $method->icon,
                'name' => $method->name,
                'display_name' => $method->display_name,
                'currency' => $method->currency,
                'type' => $method->type,
                'amounts' => $method->amounts,
                'max_amount' => $method->max_amount ? (float)$method->max_amount : null,
                'min_amount' => $method->min_amount ? (float)$method->min_amount : null,
                'sort_id' => $method->sort_id,
                'crypto_info' => $method->crypto_info,
                'fields' => $method->fields,
            ];
        });

        return $this->responseList($result->toArray());
    }
}
