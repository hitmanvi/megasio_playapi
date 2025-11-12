<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Translation;
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
        $locale = $this->getLocale($request);
        $name = $request->input('name');

        $query = Brand::query()
            ->enabled()
            ->ordered();

        // 按名称搜索（支持原始名称和翻译名称）
        if (!empty($name)) {
            $translationIds = Translation::where('translatable_type', Brand::class)
                ->where('field', 'name')
                ->where('locale', $locale)
                ->where('value', 'like', "%{$name}%")
                ->pluck('translatable_id');

            $query->where(function ($q) use ($name, $translationIds) {
                $q->where('name', 'like', "%{$name}%");
                if ($translationIds->isNotEmpty()) {
                    $q->orWhereIn('id', $translationIds);
                }
            });
        }

        $brands = $query->paginate($request->input('per_page', 10));

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
