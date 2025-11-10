<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Game;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    /**
     * 根据游戏ID获取推荐品牌列表
     *
     * @param int $gameId 游戏ID
     * @param string $locale 语言代码
     * @param int $limit 返回数量限制
     * @return Collection
     */
    public function getRecommendedBrands(int $gameId, string $locale = 'en', int $limit = 10): Collection
    {
        $game = Game::with(['category', 'brand', 'themes'])->enabled()->find($gameId);
        
        if (!$game || !$game->brand_id) {
            return collect();
        }

        $recommendedBrands = collect();
        $excludeIds = [$game->brand_id];

        // 优先推荐同分类游戏所属的品牌
        if ($game->category_id) {
            $sameCategoryBrandIds = Game::query()
                ->enabled()
                ->where('category_id', $game->category_id)
                ->where('brand_id', '!=', $game->brand_id)
                ->distinct()
                ->pluck('brand_id')
                ->toArray();
            
            if (!empty($sameCategoryBrandIds)) {
                $sameCategoryBrands = Brand::query()
                    ->enabled()
                    ->whereIn('id', $sameCategoryBrandIds)
                    ->whereNotIn('id', $excludeIds)
                    ->ordered()
                    ->limit($limit)
                    ->get();
                
                $recommendedBrands = $recommendedBrands->merge($sameCategoryBrands);
                $excludeIds = array_merge($excludeIds, $sameCategoryBrands->pluck('id')->toArray());
            }
        }

        // 如果还不够，推荐同主题游戏所属的品牌
        if ($recommendedBrands->count() < $limit && $game->themes->isNotEmpty()) {
            $themeIds = $game->themes->pluck('id')->toArray();
            $sameThemeBrandIds = Game::query()
                ->enabled()
                ->whereHas('themes', function ($q) use ($themeIds) {
                    $q->whereIn('themes.id', $themeIds);
                })
                ->where('brand_id', '!=', $game->brand_id)
                ->distinct()
                ->pluck('brand_id')
                ->toArray();
            
            if (!empty($sameThemeBrandIds)) {
                $sameThemeBrands = Brand::query()
                    ->enabled()
                    ->whereIn('id', $sameThemeBrandIds)
                    ->whereNotIn('id', $excludeIds)
                    ->ordered()
                    ->limit($limit - $recommendedBrands->count())
                    ->get();
                
                $recommendedBrands = $recommendedBrands->merge($sameThemeBrands);
                $excludeIds = array_merge($excludeIds, $sameThemeBrands->pluck('id')->toArray());
            }
        }

        // 如果还不够，推荐其他启用的品牌
        if ($recommendedBrands->count() < $limit) {
            $otherBrands = Brand::query()
                ->enabled()
                ->whereNotIn('id', $excludeIds)
                ->ordered()
                ->limit($limit - $recommendedBrands->count())
                ->get();
            
            $recommendedBrands = $recommendedBrands->merge($otherBrands);
        }

        // 限制返回数量并去重
        return $recommendedBrands->unique('id')->take($limit);
    }

    /**
     * 格式化品牌列表数据
     *
     * @param Collection $brands
     * @param string $locale
     * @return array
     */
    public function formatBrandsList(Collection $brands, string $locale = 'en'): array
    {
        return $brands->map(function ($brand) use ($locale) {
            return [
                'id' => $brand->id,
                'name' => $brand->getName($locale),
                'provider' => $brand->provider,
                'sort_id' => $brand->sort_id,
            ];
        })->toArray();
    }
}

