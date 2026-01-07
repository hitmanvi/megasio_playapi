<?php

namespace App\Services;

use App\Contracts\GameProviderInterface;
use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\GameProviders\GameProviderFactory;
use App\Models\Game;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GameService
{
    protected UserRecentGameService $userRecentGameService;

    public function __construct(UserRecentGameService $userRecentGameService)
    {
        $this->userRecentGameService = $userRecentGameService;
    }
    /**
     * 获取游戏列表（分页）
     *
     * @param array $filters 筛选条件
     * @param string $sort 排序方式
     * @param string $locale 语言代码
     * @param int $perPage 每页数量
     * @return LengthAwarePaginator
     */
    public function getGamesPaginated(array $filters = [], string $sort = 'new', string $locale = 'en', int $perPage = 20): LengthAwarePaginator
    {
        $query = Game::query()
            ->enabled()
            ->with(['brand', 'category', 'themes']);

        // 按 category_id 筛选（支持数组）
        if (!empty($filters['category_id']) && is_array($filters['category_id'])) {
            $query->whereIn('category_id', $filters['category_id']);
        }

        // 按 brand_id 筛选（支持数组）
        if (!empty($filters['brand_id']) && is_array($filters['brand_id'])) {
            $query->whereIn('brand_id', $filters['brand_id']);
        }

        // 按 theme_id 筛选（支持数组）
        if (!empty($filters['theme_id']) && is_array($filters['theme_id'])) {
            $query->whereHas('themes', function ($q) use ($filters) {
                $q->whereIn('themes.id', $filters['theme_id']);
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

        return $query->paginate($perPage);
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
                'has_demo' => $game->has_demo,
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
            'has_demo' => $game->has_demo,
        ];
    }

    /**
     * 应用排序
     *
     * @param Builder $query
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
     * 获取推荐游戏列表（分页）
     *
     * @param int $gameId 当前游戏ID
     * @param int $perPage 每页数量
     * @return LengthAwarePaginator
     */
    public function getRecommendedGamesPaginated(int $gameId, int $perPage = 20): LengthAwarePaginator
    {
        return Game::query()
            ->enabled()
            ->where('id', '!=', $gameId)
            ->with(['brand', 'category', 'themes'])
            ->ordered()
            ->paginate($perPage);
    }

    /**
     * 获取游戏demo地址
     *
     * @param int $gameId
     * @param string $currency
     * @return string|null
     */
    public function getGameDemoUrl(int $gameId, string $currency): ?string
    {
        $game = Game::with('brand')->find($gameId);
        if (!$game || !$game->brand) {
            return null;
        }

        // 如果游戏有直接配置的 demo_url，优先使用
        if ($game->demo_url) {
            return $game->demo_url;
        }

        $providerName = $game->brand->provider;
        if (!$providerName) {
            return null;
        }

        try {
            // 创建 provider 实例，传入 currency
            $provider = GameProviderFactory::create($providerName, $currency);
            return $provider->demo($game->out_id);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * 获取游戏 session 地址
     *
     * @param int $gameId
     * @param int $userId
     * @param string $currency
     * @return string
     * @throws \Exception
     */
    public function getGameSessionUrl(int $gameId, int $userId, string $currency): string
    {
        $game = Game::with('brand')->find($gameId);
        if (!$game || !$game->brand) {
            throw new Exception(ErrorCode::NOT_FOUND, 'Game not found');
        }

        $providerName = $game->brand->provider;
        if (!$providerName) {
            throw new Exception(ErrorCode::NOT_FOUND, 'Game provider not configured');
        }

        try {
            // 创建 provider 实例，传入 currency
            /** @var GameProviderInterface $provider */
            $provider = GameProviderFactory::create($providerName, $currency);
            /** @var string $sessionUrl */
            $sessionUrl = $provider->session((string) $userId, (string) $game->out_id);
            return $sessionUrl;
        } catch (\InvalidArgumentException $e) {
            throw new Exception(ErrorCode::NOT_FOUND, 'Game provider not found: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new Exception(ErrorCode::INTERNAL_ERROR, 'Failed to create game session: ' . $e->getMessage());
        }
    }

    public function getGameByProviderAndOutId(string $provider, string $outId): Game
    {
        return Game::where('provider', $provider)->where('out_id', $outId)->first();
    }

    /**
     * 获取用户最近游玩的游戏列表（分页）
     * 优先从 Redis 缓存读取，缓存未命中则从数据库加载
     *
     * @param int $userId 用户ID
     * @param string $sort 排序方式: recent(最近游玩), play_count(游玩次数), max_multiplier(最大倍数)
     * @param int $perPage 每页数量
     * @param int $page 页码
     * @return LengthAwarePaginator
     */
    public function getRecentPlayedGamesPaginated(int $userId, string $sort = 'recent', int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        return $this->userRecentGameService->getRecentGames($userId, $sort, $page, $perPage);
    }

    /**
     * 格式化最近游玩游戏列表数据
     *
     * @param Collection $items
     * @param string $locale
     * @return array
     */
    public function formatRecentGamesList($items, string $locale = 'en'): array
    {
        return $items->map(function ($item) use ($locale) {
            $game = $item['game'];
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
                'has_demo' => $game->has_demo,
                'play_count' => $item['play_count'],
                'max_multiplier' => $item['max_multiplier'],
                'last_played_at' => $item['last_played_at'],
            ];
        })->toArray();
    }
}
