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
        $locale = $request->input('locale', 'en');

        $categories = GameCategory::query()
            ->enabled()
            ->ordered()
            ->get();

        $result = $categories->map(function ($category) use ($locale) {
            return [
                'id' => $category->id,
                'name' => $category->getName($locale),
                'icon' => $category->icon,
                'sort_id' => $category->sort_id,
            ];
        });

        return $this->responseList($result->toArray());
    }
}
