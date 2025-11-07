<?php

namespace App\Http\Controllers;

class UtilsController extends Controller
{
    public function timestamp()
    {
        return $this->responseItem([
            'timestamp' => time(),
        ]);
    }

    /**
     * 获取应用设置
     */
    public function settings()
    {
        return $this->responseItem([
            'supported_locales' => ['en', 'zh-CN', 'ja', 'ko'],
            'supported_currencies' => config('currency.supported_currencies', []),
            'default_currency' => config('currency.default_currency', 'USD'),
            'app_limit' => config('app.app_limit', 10),
            'web_limit' => config('app.web_limit', 10),
        ]);
    }
}