<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Game extends Model
{
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
        'sort_id',
        'enabled',
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
