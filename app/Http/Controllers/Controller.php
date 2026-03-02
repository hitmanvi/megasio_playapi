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

    /**
     * 从请求 header 中获取 Kochava 设备信息
     *
     * @param Request $request
     * @return array{kochava_device_id: string, device_ids: array, device_ua: string, origination_ip: string, app_version: string, device_ver: string, usertime: int}
     */
    protected function getDeviceInfo(Request $request): array
    {
        $deviceIds = [];
        if ($request->header('X-Kochava-Idfa')) {
            $deviceIds['idfa'] = $request->header('X-Kochava-Idfa');
        }
        if ($request->header('X-Kochava-Android-Id')) {
            $deviceIds['android_id'] = $request->header('X-Kochava-Android-Id');
        }

        return [
            'kochava_device_id' => $request->header('X-Kochava-Device-Id', ''),
            'device_ids' => $deviceIds,
            'device_ua' => $request->header('User-Agent', ''),
            'origination_ip' => $request->ip() ?? '0.0.0.0',
            'app_version' => $request->header('X-App-Version', ''),
            'device_ver' => $request->header('X-Device-Ver', ''),
            'usertime' => (int) ($request->header('X-Usertime') ?? time()),
            'fbc' => $request->header('X-Fbc', ''),
            'fbp' => $request->header('X-Fbp', ''),
        ];
    }
}
