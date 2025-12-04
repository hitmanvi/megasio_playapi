<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Game extends Model
{
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'languages' => 'array',
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
}
