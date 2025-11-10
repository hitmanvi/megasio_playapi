<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    /**
     * 获取支持的货币列表
     */
    public function index(): JsonResponse
    {
        $currencies = Currency::enabled()
            ->ordered()
            ->get()
            ->map(function ($currency) {
                return [
                    'code' => $currency->code,
                    'symbol' => $currency->symbol,
                    'icon' => $currency->icon,
                ];
            })
            ->toArray();

        return $this->responseList($currencies);
    }
}
