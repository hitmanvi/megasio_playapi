<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    /**
     * Currency type constants.
     */
    const TYPE_FIAT = 'fiat';
    const TYPE_CRYPTO = 'crypto';
    const TYPE_VIRTUAL = 'virtual';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'symbol',
        'icon',
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
    ];

    /**
     * Scope to filter enabled currencies.
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
        return $query->orderBy('sort_id')->orderBy('code');
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get all currency types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_FIAT,
            self::TYPE_CRYPTO,
            self::TYPE_VIRTUAL,
        ];
    }
}
