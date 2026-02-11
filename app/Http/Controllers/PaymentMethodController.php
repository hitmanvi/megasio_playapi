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
     * 支持根据 currency, type, is_fiat, ids 进行筛选（均为可选参数）
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentMethod::query();

        // 筛选：IDs（可选，数组格式）
        if ($request->has('ids') && is_array($request->input('ids'))) {
            $ids = array_filter(array_map('intval', $request->input('ids')));
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        // 筛选：货币类型（可选）
        if ($request->has('currency')) {
            $query->byCurrency($request->input('currency'));
        }

        // 筛选：支付类型 (deposit/withdraw)（可选）
        if ($request->has('type')) {
            $query->byType($request->input('type'));
        }

        // 筛选：是否法币（可选）
        if ($request->has('is_fiat')) {
            $query->byIsFiat(filter_var($request->input('is_fiat'), FILTER_VALIDATE_BOOLEAN));
        }

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
                'default_amount' => $method->default_amount ? (float)$method->default_amount : null,
                'sort_id' => $method->sort_id,
                'crypto_info' => $method->crypto_info,
                'fields' => $method->fields,
                'extra_step_type' => $method->extra_step_type,
                'extra_step_fields' => $method->extra_step_fields,
            ];
        });

        return $this->responseList($result->toArray());
    }
}
