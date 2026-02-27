<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Models\GameGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class GameGroupController extends Controller
{
    /**
     * 获取游戏群组列表
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->input('category');
        $locale = $this->getLocale($request);

        $perPage = (int) $request->input('per_page', 20);

        $query = GameGroup::query()
            ->enabled()
            ->ordered();

        if ($category) {
            $query->byCategory($category);
        }

        $groupsPaginator = $query->paginate($perPage);

        // 格式化返回数据，包含翻译和多语言名称
        $result = $groupsPaginator->getCollection()->map(function ($group) use ($locale) {
            return [
                'id' => $group->id,
                'category' => $group->category,
                'sort_id' => $group->sort_id,
                'name' => $group->name ?: $group->getNameTranslation($locale),
                'app_limit' => $group->app_limit,
                'web_limit' => $group->web_limit,
            ];
        });

        // 创建分页器，使用格式化后的数据
        $formattedPaginator = new LengthAwarePaginator(
            $result,
            $groupsPaginator->total(),
            $groupsPaginator->perPage(),
            $groupsPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }

    /**
     * 获取指定分类的游戏群组
     */
    public function getByCategory(string $category, Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);

        $perPage = (int) $request->input('per_page', 20);

        $groupsPaginator = GameGroup::where('category', $category)
            ->enabled()
            ->ordered()
            ->paginate($perPage);

        $result = $groupsPaginator->getCollection()->map(function ($group) use ($locale) {
            return [
                'id' => $group->id,
                'category' => $group->category,
                'sort_id' => $group->sort_id,
                'name' => $group->name ?: $group->getNameTranslation($locale),
                'app_limit' => $group->app_limit,
                'web_limit' => $group->web_limit,
            ];
        });

        // 创建分页器，使用格式化后的数据
        $formattedPaginator = new LengthAwarePaginator(
            $result,
            $groupsPaginator->total(),
            $groupsPaginator->perPage(),
            $groupsPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }

    /**
     * 获取 support_bonus 群组详情
     */
    public function getSupportBonusDetail(Request $request): JsonResponse
    {
        $locale = $this->getLocale($request);

        $group = GameGroup::supportBonus()
            ->enabled()
            ->ordered()
            ->first();

        if (!$group) {
            return $this->error(ErrorCode::NOT_FOUND, 'Support bonus group not found');
        }

        $result = [
            'id' => $group->id,
            'category' => $group->category,
            'name' => $group->name ?: $group->getNameTranslation($locale),
            'sort_id' => $group->sort_id,
            'app_limit' => $group->app_limit,
            'web_limit' => $group->web_limit,
        ];

        return $this->responseItem($result);
    }

    /**
     * 获取游戏群组详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = $this->getLocale($request);

        $group = GameGroup::findOrFail($id);

        if (!$group->enabled) {
            return $this->error(ErrorCode::NOT_FOUND, 'Game group not found or disabled');
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

        // 处理 brand_id、theme_id 过滤（支持单个值或数组）
        $brandIds = $request->input('brand_id');
        $themeIds = $request->input('theme_id');
        if ($brandIds && !is_array($brandIds)) {
            $brandIds = [$brandIds];
        }
        if ($themeIds && !is_array($themeIds)) {
            $themeIds = [$themeIds];
        }

        $perPage = (int) $request->input('per_page', 20);

        $gamesQuery = $group->games();
        if (!empty($brandIds)) {
            $gamesQuery->whereIn('brand_id', $brandIds);
        }
        if (!empty($themeIds)) {
            $gamesQuery->whereHas('themes', function ($q) use ($themeIds) {
                $q->whereIn('themes.id', $themeIds);
            });
        }

        $gamesPaginator = $gamesQuery->paginate($perPage);

        $result = $gamesPaginator->getCollection()->map(function ($game) use ($locale) {
            return [
                'id' => $game->id,
                'name' => $game->name,
                'thumbnail' => $game->thumbnail,
                'sort_id' => $game->pivot->sort_id ?? 0,
                'support_bonus' => $game->support_bonus,
            ];
        });

        // 创建新的分页器，使用格式化后的数据
        $formattedPaginator = new LengthAwarePaginator(
            $result,
            $gamesPaginator->total(),
            $gamesPaginator->perPage(),
            $gamesPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }
}
