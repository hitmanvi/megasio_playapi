<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class Controller
{
    use AuthorizesRequests, ValidatesRequests, ResponseTrait;

    /**
     * 获取客户端 IP
     */
    protected function getClientIp(Request $request): string
    {
        return $request->ip() ?? '0.0.0.0';
    }

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

        $info = [
            'kochava_device_id' => $request->header('x-mmp-kochava-device-id', ''),
            'device_ids' => $deviceIds,
            'device_ua' => $request->header('user-agent', ''),
            'origination_ip' => $this->getClientIp($request),
            'app_version' => $request->header('x-app-version', ''),
            'device_ver' => $request->header('x-device-ver', ''),
            'usertime' => (int) ($request->header('x-usertime') ?? time()),
            'fbc' => $request->header('x-mmp-fbc', ''),
            'fbp' => $request->header('x-mmp-fbp', ''),
            'env_type' => $request->header('x-mmp-env-type', ''),
            'click_id' => $request->header('x-mmp-click-id', ''),
            'ko_click_id' => $request->header('x-mmp-ko-click-id', ''),
            'device_id' => $request->header('x-mmp-device-id', ''),
            'x-mmp-native-user-agent' => $request->header('x-mmp-native-user-agent', ''),
        ];

        return $info;
    }

    /**
     * 获取用于事件上报的 device info
     * - origination_ip: 来自 register_info（注册时 IP），Kochava 使用
     * - current_ip: 当前请求 IP，Facebook 使用
     *
     * @param Request $request
     * @param User|null $user 有用户时从 register_info 取 origination_ip
     */
    protected function getDeviceInfoForEvent(Request $request, ?User $user = null): array
    {
        $info = $this->getDeviceInfo($request);
        $currentIp = $this->getClientIp($request);
        $info['current_ip'] = $currentIp;

        if ($user) {
            $registerInfo = UserMeta::getLatest($user->id, UserMeta::KEY_REGISTER_INFO);
            if ($registerInfo) {
                $decoded = json_decode($registerInfo, true);
                if (!empty($decoded['origination_ip'])) {
                    $info['origination_ip'] = $decoded['origination_ip'];
                }
            }
        }

        $info['event_source_url'] = config('app.web_url', config('app.url', 'https://example.com'));
        return $info;
    }
}
