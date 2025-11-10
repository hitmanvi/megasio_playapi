<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    protected BrandService $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    /**
     * 获取品牌列表
     */
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query()
            ->enabled()
            ->ordered()
            ->paginate($request->input('per_page', 10));

        return $this->responseListWithPaginator($brands);
    }

    /**
     * 获取推荐品牌列表
     */
    public function recommend(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $id = (int) $request->input('id');
        $locale = $this->getLocale($request);
        $limit = (int) $request->input('limit', 10);

        $brands = $this->brandService->getRecommendedBrands($id, $locale, $limit);
        $result = $this->brandService->formatBrandsList($brands, $locale);

        return $this->responseList($result);
    }
}
