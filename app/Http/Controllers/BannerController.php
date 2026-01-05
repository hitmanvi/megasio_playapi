<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /**
     * 获取当前可用的Banners
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $platform = $request->input('platform', 'web'); // web or app

        $banners = Banner::getCurrentBanners($type);

        // 根据平台返回对应的图片和跳转URL
        $result = $banners->map(function ($banner) use ($platform) {
            return [
                'id' => $banner->id,
                'type' => $banner->type,
                'img_url' => $platform === 'app' ? $banner->app_img : $banner->web_img,
                'href' => $platform === 'app' ? $banner->app_href : $banner->web_href,
                'description' => $banner->description,
                'sort_id' => $banner->sort_id,
            ];
        });

        return $this->responseList($result->toArray());
    }
}
