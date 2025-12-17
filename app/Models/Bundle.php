<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Bundle extends Model
{
    protected const CACHE_PREFIX = 'bundle:';
    protected const CACHE_TTL = 3600;
    protected const CACHE_LIST_KEY = 'bundle:list:enabled';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'icon',
        'gold_coin',
        'social_coin',
        'original_price',
        'discount_price',
        'currency',
        'stock',
        'enabled',
        'sort_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'gold_coin' => 'decimal:8',
        'social_coin' => 'decimal:8',
        'original_price' => 'decimal:8',
        'discount_price' => 'decimal:8',
        'stock' => 'integer',
        'enabled' => 'boolean',
        'sort_id' => 'integer',
    ];

    /**
     * 获取当前有效价格
     */
    public function getCurrentPrice(): string
    {
        if ($this->isDiscountActive()) {
            return $this->discount_price;
        }
        return $this->original_price;
    }

    /**
     * 检查折扣是否有效
     */
    public function isDiscountActive(): bool
    {
        return $this->discount_price !== null;
    }

    /**
     * 获取折扣百分比
     */
    public function getDiscountPercentage(): ?int
    {
        if (!$this->isDiscountActive() || $this->original_price <= 0) {
            return null;
        }

        $discount = (1 - ($this->discount_price / $this->original_price)) * 100;
        return (int) round($discount);
    }

    /**
     * 检查是否有库存
     */
    public function hasStock(): bool
    {
        // null 表示无限库存
        if ($this->stock === null) {
            return true;
        }
        return $this->stock > 0;
    }

    /**
     * 检查是否可购买
     */
    public function isPurchasable(): bool
    {
        return $this->enabled && $this->hasStock();
    }

    /**
     * 减少库存
     */
    public function decrementStock(): bool
    {
        if ($this->stock === null) {
            return true; // 无限库存
        }

        if ($this->stock <= 0) {
            return false;
        }

        $this->decrement('stock');
        $this->clearCache();
        return true;
    }

    /**
     * 关联的购买记录
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(BundlePurchase::class);
    }

    /**
     * Scope: 只获取启用的
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: 有库存的
     */
    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('stock')->orWhere('stock', '>', 0);
        });
    }

    /**
     * Scope: 按排序排列
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_id', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Scope: 按货币筛选
     */
    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * 获取启用的Bundle列表（带缓存）
     */
    public static function getEnabledList(string $currency = 'USD'): array
    {
        $cacheKey = self::CACHE_LIST_KEY . ':' . $currency;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($currency) {
            return self::enabled()
                ->byCurrency($currency)
                ->ordered()
                ->get()
                ->toArray();
        });
    }

    /**
     * 通过ID获取Bundle（带缓存）
     */
    public static function findCached(int $id): ?self
    {
        $cacheKey = self::CACHE_PREFIX . 'id:' . $id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            return static::find($id);
        });
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'id:' . $this->id);
        // 清除所有货币的列表缓存
        $currencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];
        foreach ($currencies as $currency) {
            Cache::forget(self::CACHE_LIST_KEY . ':' . $currency);
        }
    }

    /**
     * 模型事件
     */
    protected static function booted(): void
    {
        static::saved(function (Bundle $bundle) {
            $bundle->clearCache();
        });

        static::deleted(function (Bundle $bundle) {
            $bundle->clearCache();
        });
    }

    /**
     * 格式化输出
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'gold_coin' => (float) $this->gold_coin,
            'social_coin' => (float) $this->social_coin,
            'original_price' => (float) $this->original_price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'current_price' => (float) $this->getCurrentPrice(),
            'currency' => $this->currency,
            'stock' => $this->stock,
            'in_stock' => $this->hasStock(),
            'is_discount_active' => $this->isDiscountActive(),
            'discount_percentage' => $this->getDiscountPercentage(),
        ];
    }
}
