<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GameGroup extends Model
{
    public const NAME_SUPPORT_BONUS = 'Support Bonus';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category',
        'name',
        'sort_id',
        'app_limit',
        'web_limit',
        'enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sort_id' => 'integer',
        'app_limit' => 'integer',
        'web_limit' => 'integer',
        'enabled' => 'boolean',
    ];

    /**
     * Category constants.
     */
    const CATEGORY_EVENT = 'Event';
    const CATEGORY_SYSTEM = 'System';
    /**
     * Get the games in this group.
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_group_game')
                    ->withPivot('sort_id')
                    ->withTimestamps()
                    ->orderBy('sort_id', 'asc');
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter support_bonus group (by name = NAME_SUPPORT_BONUS).
     */
    public function scopeSupportBonus($query)
    {
        return $query->where('name', self::NAME_SUPPORT_BONUS);
    }

    /**
     * Scope to filter enabled groups.
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
     * Get games for a specific platform.
     */
    public function getGamesForPlatform($platform = 'web'): \Illuminate\Database\Eloquent\Collection
    {
        $limit = $platform === 'app' ? $this->app_limit : $this->web_limit;
        
        if (!$limit) {
            return $this->games;
        }

        return $this->games()->limit($limit)->get();
    }

    /**
     * Get translations for this game group.
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

        return $translation ? $translation->value : null;
    }

    /**
     * Get all name translations.
     */
    public function getNames(): array
    {
        return $this->translations()
                   ->where('field', 'name')
                   ->pluck('value', 'locale')
                   ->toArray();
    }
}
