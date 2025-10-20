<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use Translatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'provider',
        'restricted_region',
        'sort_id',
        'enabled',
        'maintain_start',
        'maintain_end',
        'maintain_auto',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'restricted_region' => 'array',
        'enabled' => 'boolean',
        'maintain_auto' => 'boolean',
        'maintain_start' => 'datetime',
        'maintain_end' => 'datetime',
        'sort_id' => 'integer',
    ];

    /**
     * Get the brand details for this brand.
     */
    public function brandDetails(): HasMany
    {
        return $this->hasMany(BrandDetail::class);
    }

    /**
     * Scope to filter by enabled status.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to order by sort_id.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_id');
    }

    /**
     * Check if brand is in maintenance.
     */
    public function isInMaintenance(): bool
    {
        if (!$this->maintain_start || !$this->maintain_end) {
            return false;
        }

        $now = now();
        return $now->between($this->maintain_start, $this->maintain_end);
    }

    /**
     * Check if region is restricted.
     */
    public function isRegionRestricted(string $region): bool
    {
        if (!$this->restricted_region) {
            return false;
        }

        return in_array($region, $this->restricted_region);
    }

    /**
     * Get the translated name for the current locale.
     */
    public function getName(?string $locale = null): ?string
    {
        return $this->getTranslatedAttribute('name', $locale);
    }

    /**
     * Set the translated name for a specific locale.
     */
    public function setName(string $name, ?string $locale = null): void
    {
        $this->setTranslation('name', $name, $locale);
    }

    /**
     * Get all translated names.
     */
    public function getAllNames()
    {
        return $this->getTranslations('name');
    }

    /**
     * Set multiple translated names.
     */
    public function setNames(array $names): void
    {
        $this->setTranslations('name', $names);
    }
}
