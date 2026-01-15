<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Enums\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    /**
     * 获取文章列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $articles = Article::query()
            ->with('group')
            ->enabled()
            ->byGroupId($request->input('group_id'))
            ->search($request->input('keyword'))
            ->ordered()
            ->paginate($request->input('per_page', 10));

        // 格式化返回数据
        $articles->getCollection()->transform(function ($article) {
            return $this->formatArticle($article, false);
        });

        return $this->responseListWithPaginator($articles);
    }

    /**
     * 获取单个文章详情
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $withGroup = $request->boolean('with_group', true);

        $query = Article::query()
            ->enabled()
            ->where('id', $id);

        if ($withGroup) {
            $query->with('group');
        }

        $article = $query->first();

        if (!$article) {
            return $this->error(ErrorCode::NOT_FOUND, 'Article not found');
        }

        return $this->responseItem($this->formatArticle($article, true, $withGroup));
    }


    /**
     * 格式化文章数据
     * 
     * @param Article $article
     * @param bool $includeContent
     * @param bool $includeGroup
     * @return array
     */
    protected function formatArticle(Article $article, bool $includeContent = true, bool $includeGroup = true): array
    {
        $data = [
            'id' => $article->id,
            'title' => $article->title,
            'group_id' => $article->group_id,
            'enabled' => $article->enabled,
            'sort_id' => $article->sort_id,
            'created_at' => $article->created_at,
            'updated_at' => $article->updated_at,
        ];

        if ($includeContent) {
            $data['content'] = $article->content;
        }

        if ($includeGroup && $article->relationLoaded('group') && $article->group) {
            $data['group'] = [
                'id' => $article->group->id,
                'name' => $article->group->name,
                'icon' => $article->group->icon,
            ];
        }

        return $data;
    }
}
