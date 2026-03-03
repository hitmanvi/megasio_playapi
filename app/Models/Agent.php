<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'promotion_code',
        'facebook_pixel_id',
        'facebook_access_token',
        'kochava_app_id',
        'status',
    ];

    protected $hidden = [
        'facebook_access_token',
    ];

    /**
     * 根据推广码查找 Agent
     */
    public static function findByPromotionCode(string $code): ?self
    {
        return static::where('promotion_code', $code)->where('status', self::STATUS_ACTIVE)->first();
    }

    /**
     * 是否已配置 Facebook Conversions
     */
    public function hasFacebookConversions(): bool
    {
        return !empty($this->facebook_pixel_id) && !empty($this->facebook_access_token);
    }

    /**
     * 是否已配置 Kochava（开关由 config services.kochava.enabled 控制）
     */
    public function hasKochava(): bool
    {
        return !empty($this->kochava_app_id);
    }
}
