<?php

namespace App\Http\Controllers;

use App\Models\GameGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GameGroupController extends Controller
{
    /**
     * 获取游戏群组列表
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->input('category');
        $locale = $this->getLocale($request);

        $query = GameGroup::query()
            ->enabled()
            ->ordered();

        if ($category) {
            $query->byCategory($category);
        }

        $groups = $query->get();

        // 格式化返回数据，包含翻译和多语言名称
        $result = $groups->map(function ($group) use ($locale) {
            return [
                'id' => $group->id,
                'category' => $group->category,
                'sort_id' => $group->sort_id,
                'name' => $group->name ?: $group->getNameTranslation($locale),
                'app_limit' => $group->app_limit,
                'web_limit' => $group->web_limit,
            ];
        });

        return $this->responseList($result->toArray());
    }

    /**
     * 获取指定分类的游戏群组
     */
    public function getByCategory(string $category, Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);

        $groups = GameGroup::where('category', $category)
            ->enabled()
            ->ordered()
            ->get();

        $result = $groups->map(function ($group) use ($locale) {
            return [
                'id' => $group->id,
                'category' => $group->category,
                'sort_id' => $group->sort_id,
                'name' => $group->name ?: $group->getNameTranslation($locale),
                'app_limit' => $group->app_limit,
                'web_limit' => $group->web_limit,
            ];
        });

        return $this->responseList($result->toArray());
    }

    /**
     * 获取游戏群组详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = $this->getLocale($request);

        $group = GameGroup::findOrFail($id);

        if (!$group->enabled) {
            return $this->error(\App\Enums\ErrorCode::NOT_FOUND, 'Game group not found or disabled');
        }

        $result = [
            'id' => $group->id,
            'category' => $group->category,
            'name' => $group->name ?: $group->getNameTranslation($locale),
            'sort_id' => $group->sort_id,
            'app_limit' => $group->app_limit,
            'web_limit' => $group->web_limit,
            'enabled' => $group->enabled,
        ];

        return $this->responseItem($result);
    }

    /**
     * 获取指定群组中的游戏列表
     */
    public function getGames(Request $request, int $groupId): JsonResponse
    {
        $platform = $request->input('platform', 'web');
        $locale = $this->getLocale($request);

        $group = GameGroup::findOrFail($groupId);

        if (!$group->enabled) {
            return $this->responseItem([]);
        }

        $games = $group->getGamesForPlatform($platform);

        $result = $games->map(function ($game) use ($locale) {
            return [
                'id' => $game->id,
                'name' => $game->name,
                'thumbnail' => $game->thumbnail,
                'sort_id' => $game->pivot->sort_id ?? 0,
            ];
        });

        return $this->responseList($result->toArray());
    }
}
