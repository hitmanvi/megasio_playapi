<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    /**
     * 获取支持的货币列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = Currency::query()->enabled();

        // 按类型筛选
        if ($request->has('type')) {
            $type = $request->input('type');
            if (in_array($type, Currency::getTypes())) {
                $query->ofType($type);
            }
        }

        $currencies = $query->ordered()
            ->get()
            ->map(function ($currency) {
                return [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'type' => $currency->type,
                    'symbol' => $currency->symbol,
                    'icon' => $currency->icon,
                ];
            })
            ->toArray();

        return $this->responseList($currencies);
    }
}
