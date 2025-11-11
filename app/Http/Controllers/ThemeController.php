<?php

namespace App\Http\Controllers;

use App\Models\Theme;
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

        $themes = Theme::query()
            ->enabled()
            ->ordered()
            ->get();

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
