<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ThemeController extends Controller
{
    /**
     * 获取主题列表
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);
        $name = $request->input('name');
        $perPage = (int) $request->input('per_page', 20);

        $query = Theme::query()
            ->enabled()
            ->ordered();

        // 按名称搜索（支持原始名称和翻译名称）
        if (!empty($name)) {
            $translationIds = Translation::where('translatable_type', Theme::class)
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

        $themesPaginator = $query->paginate($perPage);

        // 格式化分页数据
        $themes = $themesPaginator->getCollection();
        $result = $themes->map(function ($theme) use ($locale) {
            return [
                'id' => $theme->id,
                'name' => $theme->getName($locale),
                'icon' => $theme->icon,
                'sort_id' => $theme->sort_id,
            ];
        });

        // 创建新的分页器，使用格式化后的数据
        $formattedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $result,
            $themesPaginator->total(),
            $themesPaginator->perPage(),
            $themesPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }

    /**
     * 获取主题详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = $this->getLocale($request);

        $theme = Theme::enabled()->findOrFail($id);

        $result = [
            'id' => $theme->id,
            'name' => $theme->getName($locale),
            'icon' => $theme->icon,
            'sort_id' => $theme->sort_id,
            'enabled' => $theme->enabled,
            'created_at' => $theme->created_at,
            'updated_at' => $theme->updated_at,
        ];

        return $this->responseItem($result);
    }
}
