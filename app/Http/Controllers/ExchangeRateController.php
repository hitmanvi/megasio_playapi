<?php

namespace App\Http\Controllers;

use App\Services\ExchangeRateService;
use App\Enums\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExchangeRateController extends Controller
{
    protected ExchangeRateService $exchangeRateService;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        $this->exchangeRateService = $exchangeRateService;
    }

    /**
     * 获取汇率列表
     * 传入主币种，返回各个币种相对于主币种的汇率
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'base' => 'required|string|max:10',
        ], [
            'base.required' => 'Base currency is required',
        ]);

        $baseCurrency = strtoupper($request->input('base'));

        // 验证基准货币是否存在
        $currency = \App\Models\Currency::where('code', $baseCurrency)->first();
        if (!$currency) {
            return $this->error(ErrorCode::VALIDATION_ERROR, [
                'base' => ['Base currency not found'],
            ]);
        }

        try {
            $rates = $this->exchangeRateService->getFormattedRates($baseCurrency);

            return $this->responseItem([
                'base_currency' => $baseCurrency,
                'rates' => $rates,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error(ErrorCode::VALIDATION_ERROR, [
                'base' => [$e->getMessage()],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get exchange rates', [
                'base' => $baseCurrency,
                'error' => $e->getMessage(),
            ]);
            return $this->error(ErrorCode::INTERNAL_ERROR, 'Failed to get exchange rates');
        }
    }
}
