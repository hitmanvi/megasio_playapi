<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

class Game extends Model
{
    /**
     * 缓存前缀
     */
    protected const CACHE_PREFIX = 'game:';

    /**
     * 缓存时间（秒）- 1小时
     */
    protected const CACHE_TTL = 3600;
    /**
     * 游戏在提供商的状态常量
     */
    const PROVIDER_STATUS_AVAILABLE = 'available';    // 可用
    const PROVIDER_STATUS_MAINTENANCE = 'maintenance'; // 维护
    const PROVIDER_STATUS_DELETED = 'deleted';        // 删除

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'brand_id',
        'category_id',
        'out_id',
        'name',
        'thumbnail',
        'demo_url',
        'sort_id',
        'enabled',
        'provider_status',
        'memo',
        'languages',
        'has_demo',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'languages' => 'array',
        'has_demo' => 'boolean',
    ];

    /**
     * Get the brand that owns the game.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the category for the game.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'category_id');
    }

    /**
     * Get the themes for the game.
     */
    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class, 'game_theme')
                    ->withTimestamps();
    }

    /**
     * Get the game groups that contain this game.
     */
    public function gameGroups(): BelongsToMany
    {
        return $this->belongsToMany(GameGroup::class, 'game_group_game')
                    ->withPivot('sort_id')
                    ->withTimestamps()
                    ->orderBy('sort_id', 'asc');
    }

    /**
     * Get all translations for this game.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get the name translation for a specific locale.
     */
    public function getNameTranslation(string $locale): ?string
    {
        $translation = $this->translations()
                           ->where('field', 'name')
                           ->where('locale', $locale)
                           ->first();

        return $translation ? $translation->value : $this->name;
    }

    /**
     * Scope to filter enabled games.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to order by sort_id.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_id', 'asc')->orderBy('id', 'asc');
    }

    /**
     * 根据 out_id 获取游戏（带缓存）
     */
    public static function findByOutId(string $outId): ?self
    {
        $cacheKey = self::CACHE_PREFIX . 'out_id:' . $outId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($outId) {
            return static::where('out_id', $outId)->first();
        });
    }

    /**
     * 根据 ID 获取游戏（带缓存）
     */
    public static function findCached(int $id): ?self
    {
        $cacheKey = self::CACHE_PREFIX . 'id:' . $id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            return static::find($id);
        });
    }

    /**
     * 清除游戏缓存
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'id:' . $this->id);
        Cache::forget(self::CACHE_PREFIX . 'out_id:' . $this->out_id);
    }

    /**
     * 保存时自动清除缓存
     */
    protected static function booted(): void
    {
        static::saved(function (Game $game) {
            $game->clearCache();
        });

        static::deleted(function (Game $game) {
            $game->clearCache();
        });
    }
}
