<?php

namespace App\Http\Controllers;

use App\Models\GameCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GameCategoryController extends Controller
{
    /**
     * 获取游戏分类列表
     */
    public function index(Request $request): JsonResponse
    {
        $categories = GameCategory::query()
            ->enabled()
            ->ordered()
            ->paginate($request->input('per_page', 10));

        return $this->responseListWithPaginator($categories);
    }
}
