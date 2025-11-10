<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class Controller
{
    use AuthorizesRequests, ValidatesRequests, ResponseTrait;

    /**
     * 从请求 header 中获取 locale，默认为 'en'
     *
     * @param Request $request
     * @return string
     */
    protected function getLocale(Request $request): string
    {
        return $request->header('Locale', 'en');
    }
}
