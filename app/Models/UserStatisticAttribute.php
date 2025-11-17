<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStatisticAttribute extends Model
{
    /**
     * Value type constants.
     */
    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'attribute_key',
        'attribute_value',
        'value_type',
    ];

    /**
     * Get the user that owns the attribute.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the typed value (accessor for typed_value).
     */
    public function getTypedValueAttribute()
    {
        $value = $this->attribute_value;

        if ($value === null) {
            return null;
        }

        return match ($this->value_type) {
            self::TYPE_INTEGER => (int) $value,
            self::TYPE_DECIMAL => (float) $value,
            self::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_JSON => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Set or update an attribute value.
     */
    public static function setStatisticAttribute(int $userId, string $key, $value, string $type = self::TYPE_STRING): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'attribute_key' => $key,
            ],
            [
                'attribute_value' => self::formatValue($value, $type),
                'value_type' => $type,
            ]
        );
    }

    /**
     * Get an attribute value.
     */
    public static function getStatisticAttribute(int $userId, string $key, $default = null)
    {
        $attribute = self::where('user_id', $userId)
            ->where('attribute_key', $key)
            ->first();

        if (!$attribute) {
            return $default;
        }

        return $attribute->typed_value;
    }

    /**
     * Format value based on type.
     */
    protected static function formatValue($value, string $type): string
    {
        if ($value === null) {
            return '';
        }

        return match ($type) {
            self::TYPE_JSON => json_encode($value),
            self::TYPE_BOOLEAN => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
