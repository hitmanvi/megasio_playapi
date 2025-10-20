<?php

namespace App\Models;

use App\Traits\Translatable;

class Tag extends Model
{
    use Translatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'icon',
        'enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Scope to filter by enabled status
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to filter disabled tags
     */
    public function scopeDisabled($query)
    {
        return $query->where('enabled', false);
    }

    /**
     * Check if tag is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if tag is disabled
     */
    public function isDisabled(): bool
    {
        return !$this->enabled;
    }

    /**
     * Get the translated name for the current locale.
     *
     * @param string|null $locale
     * @return string|null
     */
    public function getName(?string $locale = null): ?string
    {
        return $this->getTranslatedAttribute('name', $locale);
    }

    /**
     * Set the translated name for a specific locale.
     *
     * @param string $name
     * @param string|null $locale
     * @return void
     */
    public function setName(string $name, ?string $locale = null): void
    {
        $this->setTranslation('name', $name, $locale);
    }

    /**
     * Get all translated names.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllNames()
    {
        return $this->getTranslations('name');
    }

    /**
     * Set multiple translated names.
     *
     * @param array $names ['en' => 'Action', 'zh-CN' => '动作']
     * @return void
     */
    public function setNames(array $names): void
    {
        $this->setTranslations('name', $names);
    }
}
