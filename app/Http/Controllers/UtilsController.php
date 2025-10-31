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
        ]);
    }
}