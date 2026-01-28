<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Exceptions\Exception;
use App\Models\Game;
use App\Models\UserGameFavorite;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class GameFavoriteController extends Controller
{
    protected GameService $gameService;

    public function __construct(GameService $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * 获取用户收藏的游戏列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $locale = $this->getLocale($request);
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        $favoritesPaginator = UserGameFavorite::where('user_id', $user->id)
            ->with(['game.brand', 'game.category', 'game.themes'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $games = $favoritesPaginator->getCollection()->map(function ($favorite) {
            return $favorite->game;
        })->filter();

        $result = $this->gameService->formatGamesList($games, $locale);

        $formattedPaginator = new LengthAwarePaginator(
            $result,
            $favoritesPaginator->total(),
            $favoritesPaginator->perPage(),
            $favoritesPaginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseListWithPaginator($formattedPaginator);
    }

    /**
     * 添加游戏收藏
     */
    public function store(Request $request, int $gameId): JsonResponse
    {
        $user = $request->user();

        // 检查游戏是否存在且启用
        $game = Game::enabled()->find($gameId);
        if (!$game) {
            return $this->error(ErrorCode::NOT_FOUND, 'Game not found or disabled');
        }

        // 检查是否已收藏
        $existing = UserGameFavorite::where('user_id', $user->id)
            ->where('game_id', $gameId)
            ->first();

        if ($existing) {
            return $this->error(ErrorCode::OPERATION_NOT_ALLOWED, 'Game already favorited');
        }

        // 创建收藏记录
        UserGameFavorite::create([
            'user_id' => $user->id,
            'game_id' => $gameId,
        ]);

        return $this->responseItem([
            'message' => 'Game favorited successfully',
        ]);
    }

    /**
     * 取消游戏收藏
     */
    public function destroy(Request $request, int $gameId): JsonResponse
    {
        $user = $request->user();

        $favorite = UserGameFavorite::where('user_id', $user->id)
            ->where('game_id', $gameId)
            ->first();

        if (!$favorite) {
            return $this->error(ErrorCode::NOT_FOUND, 'Favorite not found');
        }

        $favorite->delete();

        return $this->responseItem([
            'message' => 'Game unfavorited successfully',
        ]);
    }

    /**
     * 检查游戏是否已收藏
     */
    public function check(Request $request, int $gameId): JsonResponse
    {
        $user = $request->user();

        $isFavorited = UserGameFavorite::where('user_id', $user->id)
            ->where('game_id', $gameId)
            ->exists();

        return $this->responseItem([
            'is_favorited' => $isFavorited,
        ]);
    }
}
