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
        $locale = $request->input('locale', 'en');

        $games = $this->gameService->getGames($filters, $sort, $locale);
        $result = $this->gameService->formatGamesList($games, $locale);

        return $this->responseList($result);
    }

    /**
     * 获取游戏详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = $request->input('locale', 'en');

        $game = $this->gameService->getGame($id);
        $result = $this->gameService->formatGameDetail($game, $locale);

        return $this->responseItem($result);
    }
}
