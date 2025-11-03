<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Collection;

class GameService
{
    /**
     * 获取游戏列表
     *
     * @param array $filters 筛选条件
     * @param string $sort 排序方式
     * @param string $locale 语言代码
     * @return Collection
     */
    public function getGames(array $filters = [], string $sort = 'new', string $locale = 'en'): Collection
    {
        $query = Game::query()
            ->enabled()
            ->with(['brand', 'category', 'themes']);

        // 按 category_id 筛选
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // 按 brand_id 筛选
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        // 按 theme_id 筛选
        if (!empty($filters['theme_id'])) {
            $query->whereHas('themes', function ($q) use ($filters) {
                $q->where('themes.id', $filters['theme_id']);
            });
        }

        // 按名称搜索（支持原始名称和翻译名称）
        if (!empty($filters['name'])) {
            $translationIds = Translation::where('translatable_type', Game::class)
                ->where('field', 'name')
                ->where('locale', $locale)
                ->where('value', 'like', "%{$filters['name']}%")
                ->pluck('translatable_id');

            $query->where(function ($q) use ($filters, $translationIds) {
                $q->where('name', 'like', "%{$filters['name']}%");
                if ($translationIds->isNotEmpty()) {
                    $q->orWhereIn('id', $translationIds);
                }
            });
        }

        // 排序
        $this->applySort($query, $sort);

        return $query->get();
    }

    /**
     * 获取游戏详情
     *
     * @param int $id 游戏ID
     * @return Game
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getGame(int $id): Game
    {
        return Game::with(['brand', 'category', 'themes'])
            ->enabled()
            ->findOrFail($id);
    }

    /**
     * 格式化游戏列表数据
     *
     * @param Collection $games
     * @param string $locale
     * @return array
     */
    public function formatGamesList(Collection $games, string $locale = 'en'): array
    {
        return $games->map(function ($game) use ($locale) {
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
        })->toArray();
    }

    /**
     * 格式化游戏详情数据
     *
     * @param Game $game
     * @param string $locale
     * @return array
     */
    public function formatGameDetail(Game $game, string $locale = 'en'): array
    {
        return [
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
    }

    /**
     * 应用排序
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $sort
     * @return void
     */
    protected function applySort($query, string $sort): void
    {
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
    }

    /**
     * 获取游戏demo地址
     *
     * @param int $gameId
     * @return string|null
     */
    public function getGameDemoUrl(int $gameId): ?string
    {
        $game = Game::with('brand')->find($gameId);
        if (!$game || !$game->brand) {
            return null;
        }

        $providerName = $game->brand->provider;
        if (!$providerName) {
            return null;
        }

        try {
            $provider = \App\GameProviders\GameProviderFactory::create($providerName);
            return $provider->demo($game->out_id, ['language' => 'en']);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
