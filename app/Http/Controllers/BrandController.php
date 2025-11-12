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
        
        // 处理 ids 参数，支持单个值或数组
        $ids = $request->input('ids');
        if ($ids && !is_array($ids)) {
            $ids = [$ids];
        }

        $query = Brand::query()
            ->enabled()
            ->ordered();

        // 按 ids 筛选（支持数组）
        if (!empty($ids) && is_array($ids)) {
            $query->whereIn('id', $ids);
        }

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

    /**
     * 获取品牌详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = $this->getLocale($request);

        $brand = Brand::enabled()->findOrFail($id);

        $result = [
            'id' => $brand->id,
            'name' => $brand->getName($locale),
            'provider' => $brand->provider,
            'restricted_region' => $brand->restricted_region,
            'sort_id' => $brand->sort_id,
            'enabled' => $brand->enabled,
            'maintain_start' => $brand->maintain_start,
            'maintain_end' => $brand->maintain_end,
            'maintain_auto' => $brand->maintain_auto,
            'created_at' => $brand->created_at,
            'updated_at' => $brand->updated_at,
        ];

        return $this->responseItem($result);
    }
}
