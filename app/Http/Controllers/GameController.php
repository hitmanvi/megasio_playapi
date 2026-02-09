<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    protected GameService $gameService;

    public function __construct(GameService $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * 获取游戏列表
     */
    public function index(Request $request): JsonResponse
    {
        // 处理数组参数，支持单个值或数组
        $categoryIds = $request->input('category_id');
        $brandIds = $request->input('brand_id');
        $themeIds = $request->input('theme_id');
        
        // 确保是数组格式
        if ($categoryIds && !is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }
        if ($brandIds && !is_array($brandIds)) {
            $brandIds = [$brandIds];
        }
        if ($themeIds && !is_array($themeIds)) {
            $themeIds = [$themeIds];
        }

        $filters = [
            'name' => $request->input('name'),
            'category_id' => $categoryIds,
            'brand_id' => $brandIds,
            'theme_id' => $themeIds,
        ];

        $sort = $request->input('sort', 'new');
        $locale = $this->getLocale($request);
        $perPage = (int) $request->input('per_page', 20);

        $gamesPaginator = $this->gameService->getGamesPaginated($filters, $sort, $locale, $perPage);
        
        // 格式化分页数据
        $games = $gamesPaginator->getCollection();
        $result = $this->gameService->formatGamesList($games, $locale);
        
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

    /**
     * 获取游戏详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = $this->getLocale($request);

        $game = $this->gameService->getGame($id);
        $result = $this->gameService->formatGameDetail($game, $locale);

        return $this->responseItem($result);
    }

    /**
     * 获取推荐游戏列表
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

        $gamesPaginator = $this->gameService->getRecommendedGamesPaginated($id, $perPage);
        
        // 格式化分页数据
        $games = $gamesPaginator->getCollection();
        $result = $this->gameService->formatGamesList($games, $locale);
        
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

    /**
     * 获取游戏 demo 地址
     */
    public function demo(Request $request, int $id): JsonResponse
    {
        $demoUrl = $this->gameService->getGameDemoUrl($id, 'USD');

        if (!$demoUrl) {
            return $this->error(ErrorCode::NOT_FOUND, 'Game demo not available');
        }

        return $this->responseItem([
            'url' => $demoUrl,
        ]);
    }

    /**
     * 获取游戏 session 地址（需要认证）
     */
    public function session(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'currency' => 'required|string',
        ]);

        $user = $request->user();
        $currency = strtoupper($request->input('currency'));

        try {
            $sessionUrl = $this->gameService->getGameSessionUrl($id, $user->id, $currency);

            return $this->responseItem([
                'url' => $sessionUrl,
            ]);
        } catch (Exception $e) {
            Log::error('Game session error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error($e->getErrorCode(), $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Game session error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error(ErrorCode::INTERNAL_ERROR, $e->getMessage());
        }
    }

    /**
     * 获取用户最近游玩的游戏列表
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $locale = $this->getLocale($request);
        $sort = $request->input('sort', 'recent'); // recent, play_count, max_multiplier
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        $gamesPaginator = $this->gameService->getRecentPlayedGamesPaginated($user->id, $sort, $perPage, $page);
        
        // 格式化分页数据
        $items = $gamesPaginator->getCollection();
        $result = $this->gameService->formatRecentGamesList($items, $locale);
        
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
