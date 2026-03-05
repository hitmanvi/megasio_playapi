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
        if ($request->header('x-mmp-idfa')) {
            $deviceIds['idfa'] = $request->header('x-mmp-idfa');
        }
        if ($request->header('x-mmp-gaid')) {
            $deviceIds['gaid'] = $request->header('x-mmp-gaid');
        }
        if ($request->header('x-mmp-idfv')) {
            $deviceIds['idfv'] = $request->header('x-mmp-idfv');
        }

        return [
            'kochava_device_id' => $request->header('x-mmp-kochava-device-id', ''),
            'device_ids' => $deviceIds,
            'device_ua' => $request->header('user-agent', ''),
            'origination_ip' => $request->ip() ?? '0.0.0.0',
            'app_version' => $request->header('x-app-version', ''),
            'device_ver' => $request->header('x-device-ver', ''),
            'usertime' => (int) ($request->header('x-usertime') ?? time()),
            'fbc' => $request->header('x-mmp-fbc', ''),
            'fbp' => $request->header('x-mmp-fbp', ''),
            'env_type' => $request->header('x-mmp-env-type', ''),
        ];
    }
}
