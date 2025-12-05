<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSyncLog extends Model
{
    /**
     * 同步状态常量
     */
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'brand_id',
        'total_count',
        'available_count',
        'maintenance_count',
        'deleted_count',
        'created_count',
        'updated_count',
        'status',
        'error_message',
        'started_at',
        'finished_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'brand_id' => 'integer',
        'total_count' => 'integer',
        'available_count' => 'integer',
        'maintenance_count' => 'integer',
        'deleted_count' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Get the brand that owns the sync log.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to order by started_at desc.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('started_at', 'desc');
    }
}
