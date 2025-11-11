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
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $id = (int) $request->input('id');
        $locale = $this->getLocale($request);
        $perPage = (int) $request->input('per_page', 20);

        $brandsPaginator = $this->brandService->getRecommendedBrandsPaginated($id, $perPage);
        
        // 格式化分页数据
        $brands = $brandsPaginator->getCollection();
        $result = $this->brandService->formatBrandsList($brands, $locale);
        
        // 创建新的分页器，使用格式化后的数据
        $formattedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $result,
            $brandsPaginator->total(),
            $brandsPaginator->perPage(),
            $brandsPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }
}
