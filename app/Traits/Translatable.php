<?php

namespace App\Traits;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;

trait Translatable
{
    /**
     * Get all translations for this model.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get translation for a specific field and locale.
     *
     * @param string $field
     * @param string|null $locale
     * @return string|null
     */
    public function getTranslation(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?: App::getLocale();
        
        // 如果已经预加载了translations关系，直接使用
        if ($this->relationLoaded('translations')) {
            $translation = $this->translations
                ->where('field', $field)
                ->where('locale', $locale)
                ->first();
                
            return $translation ? $translation->value : null;
        }
        
        // 否则查询数据库
        $translation = $this->translations()
            ->where('field', $field)
            ->where('locale', $locale)
            ->first();
            
        return $translation ? $translation->value : null;
    }

    /**
     * Set translation for a specific field and locale.
     *
     * @param string $field
     * @param string $value
     * @param string|null $locale
     * @return void
     */
    public function setTranslation(string $field, string $value, ?string $locale = null): void
    {
        $locale = $locale ?: App::getLocale();
        
        $this->translations()->updateOrCreate(
            [
                'field' => $field,
                'locale' => $locale,
            ],
            [
                'value' => $value,
            ]
        );
    }

    /**
     * Get all translations for a specific field.
     *
     * @param string $field
     * @return \Illuminate\Support\Collection
     */
    public function getTranslations(string $field)
    {
        // 如果已经预加载了translations关系，直接使用
        if ($this->relationLoaded('translations')) {
            return $this->translations
                ->where('field', $field)
                ->pluck('value', 'locale');
        }
        
        // 否则查询数据库
        return $this->translations()
            ->where('field', $field)
            ->get()
            ->pluck('value', 'locale');
    }

    /**
     * Set multiple translations for a field.
     *
     * @param string $field
     * @param array $translations ['en' => 'English', 'zh-CN' => '中文']
     * @return void
     */
    public function setTranslations(string $field, array $translations): void
    {
        foreach ($translations as $locale => $value) {
            $this->setTranslation($field, $value, $locale);
        }
    }

    /**
     * Get translated attribute with fallback.
     *
     * @param string $field
     * @param string|null $locale
     * @param string|null $fallbackLocale
     * @return string|null
     */
    public function getTranslatedAttribute(string $field, ?string $locale = null, ?string $fallbackLocale = 'en-US'): ?string
    {
        $locale = $locale ?: App::getLocale();
        
        // Try to get translation for current locale
        $translation = $this->getTranslation($field, $locale);
        if ($translation) {
            return $translation;
        }
        
        // Fallback to default locale if specified
        if ($fallbackLocale && $fallbackLocale !== $locale) {
            $translation = $this->getTranslation($field, $fallbackLocale);
            if ($translation) {
                return $translation;
            }
        }
        
        return null;
    }

    /**
     * Magic method to get translated attributes.
     * Usage: $model->name_en, $model->name_zh_cn
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        // Check if it's a translated attribute (field_locale format)
        if (preg_match('/^(.+)_([a-z]{2}(?:-[A-Z]{2})?)$/', $name, $matches)) {
            $field = $matches[1];
            $locale = str_replace('_', '-', $matches[2]);
            
            return $this->getTranslation($field, $locale);
        }
        
        return parent::__get($name);
    }

    /**
     * Get all available locales for a field.
     *
     * @param string $field
     * @return array
     */
    public function getAvailableLocales(string $field): array
    {
        // 如果已经预加载了translations关系，直接使用
        if ($this->relationLoaded('translations')) {
            return $this->translations
                ->where('field', $field)
                ->pluck('locale')
                ->toArray();
        }
        
        // 否则查询数据库
        return $this->translations()
            ->where('field', $field)
            ->pluck('locale')
            ->toArray();
    }

    /**
     * Delete all translations for a specific field.
     *
     * @param string $field
     * @return void
     */
    public function deleteTranslations(string $field): void
    {
        $this->translations()->where('field', $field)->delete();
    }

    /**
     * Delete all translations for this model.
     *
     * @return void
     */
    public function deleteAllTranslations(): void
    {
        $this->translations()->delete();
    }
}
