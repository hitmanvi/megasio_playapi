<?php

namespace App\Services;

use App\Models\Currency;

class ExchangeRateService
{
    /**
     * 汇率缓存时间（秒）
     */
    const CACHE_TTL = 3600; // 1小时

    /**
     * 获取汇率服务配置
     */
    protected function getServiceConfig(): array
    {
        return [
            'endpoint' => config('services.exchange_rate.endpoint'),
            'api_key' => config('services.exchange_rate.api_key'),
        ];
    }

    /**
     * 从外部服务获取汇率（以USD为基准）
     * 
     * @return array 格式：['USD' => 1, 'JPY' => 140, 'CNY' => 7.2, ...]
     */
    public function fetchRatesFromService(): array
    {
        // TODO: 外部服务暂未实现，使用默认汇率
        // 后续实现时，可以在这里调用外部服务API获取实时汇率
        return $this->getDefaultRates();
    }

    /**
     * 获取默认汇率（用于开发测试或服务不可用时）
     */
    protected function getDefaultRates(): array
    {
        return [
            'USD' => 1,
            'EUR' => 0.85,
            'CNY' => 7.2,
            'JPY' => 140,
            'KRW' => 1300,
            'GBP' => 0.8,
        ];
    }

    /**
     * 计算以指定货币为基准的汇率
     * 
     * @param string $baseCurrency 基准货币代码
     * @return array 格式：['USD' => 0.007, 'EUR' => 0.006, 'CNY' => 0.05, ...]
     */
    public function calculateRates(string $baseCurrency): array
    {
        // 获取以USD为基准的汇率
        $usdRates = $this->fetchRatesFromService();

        // 如果基准货币就是USD，直接返回
        if (strtoupper($baseCurrency) === 'USD') {
            return $usdRates;
        }

        // 获取基准货币相对于USD的汇率
        $baseRate = $usdRates[strtoupper($baseCurrency)] ?? null;
        
        if ($baseRate === null || $baseRate <= 0) {
            throw new \InvalidArgumentException("Base currency {$baseCurrency} not found in exchange rates");
        }

        // 计算以基准货币为单位的汇率
        // 如果 1 USD = baseRate 个基准货币
        // 那么 1 基准货币 = 1/baseRate USD
        // 对于任意货币 X，如果 1 USD = rateX 个 X
        // 那么 1 基准货币 = (1/baseRate) * rateX 个 X
        $result = [];
        foreach ($usdRates as $currency => $rate) {
            if ($currency === strtoupper($baseCurrency)) {
                $result[$currency] = 1; // 基准货币自身为1
            } else {
                $result[$currency] = $rate / $baseRate;
            }
        }

        return $result;
    }

    /**
     * 获取格式化的汇率列表（包含货币信息）
     * 
     * @param string $baseCurrency 基准货币代码
     * @return array
     */
    public function getFormattedRates(string $baseCurrency): array
    {
        $rates = $this->calculateRates($baseCurrency);
        
        // 获取所有启用的货币
        $currencies = Currency::enabled()->ordered()->get()->keyBy('code');
        
        $result = [];
        foreach ($rates as $currencyCode => $rate) {
            $currency = $currencies->get(strtoupper($currencyCode));
            if ($currency) {
                $result[] = [
                    'currency' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'rate' => round($rate, 8), // 保留8位小数
                ];
            }
        }

        return $result;
    }
}

