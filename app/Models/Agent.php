<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'account',
        'password',
        'remark',
        'status',
        'two_factor_secret',
    ];

    protected $casts = [
        'two_factor_secret' => 'encrypted',
        'password' => 'hashed',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
    ];

    /**
     * 推广链接列表（每个 promotion_code 一条）
     */
    public function links(): HasMany
    {
        return $this->hasMany(AgentLink::class, 'agent_id');
    }

    /**
     * 是否已开启二次验证（OTP/TOTP）
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->two_factor_secret);
    }

}
