<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    /**
     * 获取品牌列表
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->input('locale', 'en');

        $brands = Brand::query()
            ->enabled()
            ->ordered()
            ->get();

        $result = $brands->map(function ($brand) use ($locale) {
            return [
                'id' => $brand->id,
                'name' => $brand->getName($locale),
                'provider' => $brand->provider,
                'icon' => $brand->icon ?? null,
                'sort_id' => $brand->sort_id,
            ];
        });

        return $this->responseList($result->toArray());
    }
}
