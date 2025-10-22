<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandDetail extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'brand_id',
        'coin',
        'support',
        'configured',
        'game_count',
        'rate',
        'enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'support' => 'boolean',
        'configured' => 'boolean',
        'enabled' => 'boolean',
        'game_count' => 'integer',
        'rate' => 'decimal:4',
    ];

    /**
     * Get the brand that owns this detail.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to filter by enabled status.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to filter by support status.
     */
    public function scopeSupported($query)
    {
        return $query->where('support', true);
    }

    /**
     * Scope to filter by configured status.
     */
    public function scopeConfigured($query)
    {
        return $query->where('configured', true);
    }

    /**
     * Scope to filter by coin type.
     */
    public function scopeByCoin($query, $coin)
    {
        return $query->where('coin', $coin);
    }

    /**
     * Scope to filter by brand.
     */
    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Check if this detail is active (enabled and supported).
     */
    public function isActive(): bool
    {
        return $this->enabled && $this->support;
    }

    /**
     * Check if this detail is ready for use (active and configured).
     */
    public function isReady(): bool
    {
        return $this->isActive() && $this->configured;
    }
}
