<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleGroup extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'icon',
        'parent_id',
        'enabled',
        'sort_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'sort_id' => 'integer',
        'parent_id' => 'integer',
    ];

    /**
     * Get the parent group.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ArticleGroup::class, 'parent_id');
    }

    /**
     * Get the child groups.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ArticleGroup::class, 'parent_id');
    }

    /**
     * Get all descendants (recursive).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the articles in this group.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'group_id');
    }

    /**
     * Scope to filter enabled groups.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to filter disabled groups.
     */
    public function scopeDisabled($query)
    {
        return $query->where('enabled', false);
    }

    /**
     * Scope to filter root groups (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to filter by parent.
     */
    public function scopeByParent($query, $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    /**
     * Scope to order by sort_id.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_id', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Check if group is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if group is disabled.
     */
    public function isDisabled(): bool
    {
        return !$this->enabled;
    }

    /**
     * Check if group is root (no parent).
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Get the level of this group (0 for root, 1 for first level, etc.).
     */
    public function getLevel(): int
    {
        $level = 0;
        $parent = $this->parent;
        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }
        return $level;
    }

    /**
     * Get all ancestors (parent chain).
     */
    public function getAncestors()
    {
        $ancestors = collect();
        $parent = $this->parent;
        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }
        return $ancestors;
    }
}
