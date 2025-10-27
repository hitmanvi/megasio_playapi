<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'web_img_url',
        'app_img_url',
        'web_rule_url',
        'app_rule_url',
        'enabled',
        'sort_id',
        'started_at',
        'ended_at',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'sort_id' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Banner types constants.
     */
    const TYPE_HOME = 'home';
    const TYPE_PROMOTION = 'promotion';
    const TYPE_ADVERTISEMENT = 'advertisement';


    /**
     * Scope to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter enabled banners.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to filter disabled banners.
     */
    public function scopeDisabled($query)
    {
        return $query->where('enabled', false);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, $startDate = null, $endDate = null)
    {
        $query->where(function ($q) use ($startDate, $endDate) {
            $q->where(function ($q2) {
                $q2->whereNull('started_at')->whereNull('ended_at');
            });

            if ($startDate) {
                $q->orWhere(function ($q2) use ($startDate) {
                    $q2->where('started_at', '<=', $startDate)
                       ->where(function ($q3) use ($startDate) {
                           $q3->whereNull('ended_at')
                              ->orWhere('ended_at', '>=', $startDate);
                       });
                });
            }

            if ($endDate) {
                $q->orWhere(function ($q2) use ($endDate) {
                    $q2->where('ended_at', '>=', $endDate)
                       ->where(function ($q3) use ($endDate) {
                           $q3->whereNull('started_at')
                              ->orWhere('started_at', '<=', $endDate);
                       });
                });
            }
        });

        return $query;
    }

    /**
     * Scope to order by sort_id.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_id', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Check if banner is currently active.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $now = now();

        if ($this->started_at && $now < $this->started_at) {
            return false;
        }

        if ($this->ended_at && $now > $this->ended_at) {
            return false;
        }

        return true;
    }

    /**
     * Get banners that should be displayed now.
     */
    public static function getCurrentBanners(string $type = null)
    {
        $query = self::query()
                     ->enabled()
                     ->inDateRange(now(), now())
                     ->ordered();

        if ($type) {
            $query->byType($type);
        }

        return $query->get();
    }
}
