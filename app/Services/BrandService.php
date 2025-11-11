<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Game;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BrandService
{
    /**
     * 根据游戏ID获取推荐品牌列表（分页）
     *
     * @param int $gameId 游戏ID
     * @param int $perPage 每页数量
     * @return LengthAwarePaginator
     */
    public function getRecommendedBrandsPaginated(int $gameId, int $perPage = 20): LengthAwarePaginator
    {
        $game = Game::enabled()->find($gameId);
        
        $query = Brand::query()
            ->enabled()
            ->ordered();

        // 排除当前游戏所属的品牌
        if ($game && $game->brand_id) {
            $query->where('id', '!=', $game->brand_id);
        }

        return $query->paginate($perPage);
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

