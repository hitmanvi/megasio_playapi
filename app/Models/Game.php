<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'theme_id',
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
    ];

    /**
     * Get languages as array.
     */
    public function getLanguagesAttribute($value): array
    {
        return $value ? explode(',', $value) : [];
    }

    /**
     * Set languages from array.
     */
    public function setLanguagesAttribute($value): void
    {
        $this->attributes['languages'] = is_array($value) ? implode(',', $value) : $value;
    }
}
