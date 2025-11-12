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

        $themes = $query->get();

        $result = $themes->map(function ($theme) use ($locale) {
            return [
                'id' => $theme->id,
                'name' => $theme->getName($locale),
                'icon' => $theme->icon,
                'sort_id' => $theme->sort_id,
            ];
        });

        return $this->responseList($result->toArray());
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
