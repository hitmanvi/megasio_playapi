<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    /**
     * 获取游戏列表
     */
    public function index(Request $request): JsonResponse
    {
        $name = $request->input('name');
        $categoryId = $request->input('category_id');
        $brandId = $request->input('brand_id');
        $themeId = $request->input('theme_id');
        $sort = $request->input('sort', 'new'); // new, hot, a-z, z-a
        $locale = $request->input('locale', 'en');

        $query = Game::query()
            ->enabled()
            ->with(['brand', 'category', 'themes']);

        // 按 category_id 筛选
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // 按 brand_id 筛选
        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        // 按 theme_id 筛选
        if ($themeId) {
            $query->whereHas('themes', function ($q) use ($themeId) {
                $q->where('themes.id', $themeId);
            });
        }

        // 按名称搜索（支持原始名称和翻译名称）
        if ($name) {
            $translationIds = Translation::where('translatable_type', Game::class)
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

        // 排序
        switch ($sort) {
            case 'hot':
                $query->orderBy('sort_id', 'desc')->orderBy('id', 'desc');
                break;
            case 'a-z':
                $query->orderBy('name', 'asc');
                break;
            case 'z-a':
                $query->orderBy('name', 'desc');
                break;
            case 'new':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $games = $query->get();

        // 格式化返回数据
        $result = $games->map(function ($game) use ($locale) {
            return [
                'id' => $game->id,
                'name' => $game->getNameTranslation($locale),
                'thumbnail' => $game->thumbnail,
                'sort_id' => $game->sort_id,
                'brand' => $game->brand ? [
                    'id' => $game->brand->id,
                    'name' => $game->brand->getName($locale),
                    'provider' => $game->brand->provider,
                ] : null,
                'category' => $game->category ? [
                    'id' => $game->category->id,
                    'name' => $game->category->getName($locale),
                ] : null,
                'themes' => $game->themes->map(function ($theme) use ($locale) {
                    return [
                        'id' => $theme->id,
                        'name' => $theme->getName($locale),
                        'icon' => $theme->icon,
                    ];
                }),
                'created_at' => $game->created_at,
            ];
        });

        return $this->responseList($result->toArray());
    }

    /**
     * 获取游戏详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = $request->input('locale', 'en');

        $game = Game::with(['brand', 'category', 'themes'])
            ->enabled()
            ->findOrFail($id);

        $result = [
            'id' => $game->id,
            'name' => $game->getNameTranslation($locale),
            'thumbnail' => $game->thumbnail,
            'out_id' => $game->out_id,
            'sort_id' => $game->sort_id,
            'memo' => $game->memo,
            'languages' => $game->languages,
            'brand' => $game->brand ? [
                'id' => $game->brand->id,
                'name' => $game->brand->getName($locale),
                'provider' => $game->brand->provider,
            ] : null,
            'category' => $game->category ? [
                'id' => $game->category->id,
                'name' => $game->category->getName($locale),
                'icon' => $game->category->icon,
            ] : null,
            'themes' => $game->themes->map(function ($theme) use ($locale) {
                return [
                    'id' => $theme->id,
                    'name' => $theme->getName($locale),
                    'icon' => $theme->icon,
                ];
            }),
            'created_at' => $game->created_at,
            'updated_at' => $game->updated_at,
        ];

        return $this->responseItem($result);
    }
}
