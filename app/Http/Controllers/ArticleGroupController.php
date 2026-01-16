<?php

namespace App\Http\Controllers;

use App\Models\ArticleGroup;
use App\Enums\ErrorCode;
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
     * 获取单个分组详情（包含所有上级分组）
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $group = ArticleGroup::query()
            ->enabled()
            ->with(['children' => function ($q) {
                $q->enabled()->ordered();
            }])
            ->where('id', $id)
            ->first();

        if (!$group) {
            return $this->error(ErrorCode::NOT_FOUND, 'Article group not found');
        }

        // 获取所有上级分组
        $ancestors = $group->getAncestors();
        
        $data = $this->formatGroup($group);
        $data['ancestors'] = $ancestors->map(function ($ancestor) {
            return $this->formatGroup($ancestor);
        })->values()->toArray();
        
        // 获取子分组
        $data['children'] = $group->children->map(function ($child) {
            return $this->formatGroup($child);
        })->values()->toArray();

        return $this->responseItem($data);
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
