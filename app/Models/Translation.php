<?php

namespace App\Models;

class Translation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'field',
        'locale',
        'value',
    ];

    /**
     * Get the translatable model.
     */
    public function translatable()
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by locale.
     */
    public function scopeForLocale($query, $locale)
    {
        return $query->where('locale', $locale);
    }

    /**
     * Scope to filter by field.
     */
    public function scopeForField($query, $field)
    {
        return $query->where('field', $field);
    }

    /**
     * Scope to filter by translatable model.
     */
    public function scopeForModel($query, $model)
    {
        return $query->where('translatable_type', get_class($model))
                    ->where('translatable_id', $model->id);
    }
}