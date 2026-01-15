<?php

namespace App\Http\Controllers;

use App\Models\ArticleGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleGroupController extends Controller
{
    /**
     * 获取分组列表（支持层级结构）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $groups = ArticleGroup::query()
            ->enabled()
            ->byParentId($request->input('parent_id'))
            ->byName($request->input('name'))
            ->ordered()
            ->paginate($request->input('per_page', 10));

        // 格式化返回数据
        $groups->getCollection()->transform(function ($group) {
            return $this->formatGroup($group);
        });

        return $this->responseListWithPaginator($groups);
    }

    /**
     * 格式化分组数据
     * 
     * @param ArticleGroup $group
     * @return array
     */
    protected function formatGroup(ArticleGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'icon' => $group->icon,
            'parent_id' => $group->parent_id,
            'enabled' => $group->enabled,
            'sort_id' => $group->sort_id,
            'created_at' => $group->created_at,
            'updated_at' => $group->updated_at,
        ];
    }
}
