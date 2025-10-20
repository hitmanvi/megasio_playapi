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
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_PENDING = 'pending';

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_PENDING => 'Pending',
        ];
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter active tags
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to filter inactive tags
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope to filter pending tags
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Check if tag is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if tag is inactive
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Check if tag is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
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
