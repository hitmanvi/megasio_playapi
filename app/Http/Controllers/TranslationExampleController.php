<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TranslationExampleController extends Controller
{
    /**
     * 演示多语言翻译的使用方法
     */
    public function example()
    {
        // 创建一个标签
        $tag = Tag::create([
            'name' => 'action', // 默认名称
            'type' => 'category'
        ]);

        // 方法1: 使用setTranslation设置单个翻译
        $tag->setTranslation('name', 'Action', 'en');
        $tag->setTranslation('name', '动作', 'zh-CN');
        $tag->setTranslation('name', 'アクション', 'ja');

        // 方法2: 使用setTranslations批量设置翻译
        $tag->setTranslations('name', [
            'en' => 'Action',
            'zh-CN' => '动作',
            'ja' => 'アクション',
            'ko' => '액션'
        ]);

        // 获取翻译
        echo "英文名称: " . $tag->getTranslation('name', 'en') . "\n";
        echo "中文名称: " . $tag->getTranslation('name', 'zh-CN') . "\n";
        echo "日文名称: " . $tag->getTranslation('name', 'ja') . "\n";

        // 使用getTranslatedAttribute获取当前语言环境的翻译
        app()->setLocale('zh-CN');
        echo "当前语言环境名称: " . $tag->getTranslatedAttribute('name') . "\n";

        // 使用魔术方法获取翻译
        echo "英文名称: " . $tag->name_en . "\n";
        echo "中文名称: " . $tag->name_zh_cn . "\n";

        // 获取所有翻译
        $allNames = $tag->getAllNames();
        echo "所有翻译: " . json_encode($allNames) . "\n";

        // 获取可用的语言环境
        $availableLocales = $tag->getAvailableLocales('name');
        echo "可用语言: " . implode(', ', $availableLocales) . "\n";

        return response()->json([
            'tag' => $tag,
            'translations' => $allNames,
            'available_locales' => $availableLocales
        ]);
    }

    /**
     * 创建带有多语言标签的API端点
     */
    public function createTag(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'translations' => 'array',
            'translations.*' => 'string'
        ]);

        $tag = Tag::create([
            'name' => $request->name,
            'type' => $request->type
        ]);

        // 设置多语言翻译
        if ($request->has('translations')) {
            $tag->setTranslations('name', $request->translations);
        }

        return response()->json([
            'message' => 'Tag created successfully',
            'tag' => $tag,
            'translations' => $tag->getAllNames()
        ]);
    }

    /**
     * 获取标签的多语言信息
     */
    public function getTagWithTranslations($id)
    {
        $tag = Tag::findOrFail($id);
        
        return response()->json([
            'tag' => $tag,
            'translations' => [
                'name' => $tag->getAllNames()
            ],
            'available_locales' => $tag->getAvailableLocales('name')
        ]);
    }

    /**
     * 获取标签列表（带多语言翻译）
     */
    public function getTagsList(Request $request)
    {
        // 获取请求的语言参数，默认为当前应用语言
        $locale = $request->get('locale', app()->getLocale());
        
        // 预加载翻译关系以提高性能
        $tags = Tag::with('translations')->get();
        
        // 格式化返回数据
        $formattedTags = $tags->map(function ($tag) use ($locale) {
            return [
                'id' => $tag->id,
                'name' => $tag->name, // 原始名称
                'type' => $tag->type,
                'translated_name' => $tag->getTranslatedAttribute('name', $locale, 'en'), // 翻译后的名称，回退到英文
                'all_translations' => $tag->getAllNames(), // 所有语言的翻译
                'available_locales' => $tag->getAvailableLocales('name'),
                'created_at' => $tag->created_at,
                'updated_at' => $tag->updated_at,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formattedTags,
            'meta' => [
                'total' => $tags->count(),
                'locale' => $locale,
                'fallback_locale' => 'en'
            ]
        ]);
    }

    /**
     * 根据类型获取标签列表
     */
    public function getTagsByType(Request $request, $type)
    {
        $locale = $request->get('locale', app()->getLocale());
        
        $tags = Tag::where('type', $type)
            ->with('translations')
            ->get();
        
        $formattedTags = $tags->map(function ($tag) use ($locale) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'type' => $tag->type,
                'translated_name' => $tag->getTranslatedAttribute('name', $locale, 'en'),
                'all_translations' => $tag->getAllNames(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formattedTags,
            'meta' => [
                'type' => $type,
                'total' => $tags->count(),
                'locale' => $locale
            ]
        ]);
    }

    /**
     * 搜索标签（支持多语言搜索）
     */
    public function searchTags(Request $request)
    {
        $query = $request->get('q', '');
        $locale = $request->get('locale', app()->getLocale());
        $type = $request->get('type');
        
        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Search query is required'
            ], 400);
        }
        
        // 构建查询
        $tagsQuery = Tag::with('translations');
        
        // 按类型过滤
        if ($type) {
            $tagsQuery->where('type', $type);
        }
        
        // 搜索原始名称或翻译
        $tagsQuery->where(function ($q) use ($query, $locale) {
            // 搜索原始名称
            $q->where('name', 'like', "%{$query}%");
            
            // 搜索指定语言的翻译
            $q->orWhereHas('translations', function ($translationQuery) use ($query, $locale) {
                $translationQuery->where('field', 'name')
                    ->where('locale', $locale)
                    ->where('value', 'like', "%{$query}%");
            });
        });
        
        $tags = $tagsQuery->get();
        
        $formattedTags = $tags->map(function ($tag) use ($locale) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'type' => $tag->type,
                'translated_name' => $tag->getTranslatedAttribute('name', $locale, 'en'),
                'all_translations' => $tag->getAllNames(),
                'match_type' => $this->getMatchType($tag, $query, $locale), // 标识匹配类型
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formattedTags,
            'meta' => [
                'query' => $query,
                'locale' => $locale,
                'type' => $type,
                'total' => $tags->count()
            ]
        ]);
    }

    /**
     * 获取匹配类型（用于搜索结果）
     */
    private function getMatchType($tag, $query, $locale)
    {
        if (stripos($tag->name, $query) !== false) {
            return 'original_name';
        }
        
        $translatedName = $tag->getTranslatedAttribute('name', $locale, 'en');
        if ($translatedName && stripos($translatedName, $query) !== false) {
            return 'translated_name';
        }
        
        return 'other_translation';
    }
}
