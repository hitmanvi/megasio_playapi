<?php

namespace App\Http\Controllers;

use App\Services\GameService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
        $filters = [
            'name' => $request->input('name'),
            'category_id' => $request->input('category_id'),
            'brand_id' => $request->input('brand_id'),
            'theme_id' => $request->input('theme_id'),
        ];

        $sort = $request->input('sort', 'new');
        $locale = $this->getLocale($request);

        $games = $this->gameService->getGames($filters, $sort, $locale);
        $result = $this->gameService->formatGamesList($games, $locale);

        return $this->responseList($result);
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
        $formattedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $result,
            $gamesPaginator->total(),
            $gamesPaginator->perPage(),
            $gamesPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }
}
