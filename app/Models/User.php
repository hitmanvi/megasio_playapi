<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uid',
        'name',
        'phone',
        'area_code',
        'email',
        'password',
        'google_id',
        'status',
        'ban_reason',
        'invite_code',
        'display_currencies',
        'base_currency',
        'current_currency',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'password',
        'remember_token',
        'updated_at',
        'created_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'display_currencies' => 'array',
        ];
    }

    /**
     * 生成唯一的UID - 使用ULID
     */
    public static function generateUid(): string
    {
        return Str::ulid()->toString();
    }

    /**
     * 生成唯一的邀请码 - 8位大写字母和数字组合
     */
    public static function generateInviteCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 排除容易混淆的字符 0, O, I, 1
        $code = '';
        $maxAttempts = 100;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }

            // 检查是否已存在
            if (!static::where('invite_code', $code)->exists()) {
                return $code;
            }
        }

        // 如果100次尝试都失败，使用时间戳+随机字符
        return strtoupper(substr(Str::random(6) . time(), 0, 8));
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // 在创建用户时自动生成邀请码
        static::creating(function ($user) {
            if (empty($user->invite_code)) {
                $user->invite_code = static::generateInviteCode();
            }
        });
    }

    /**
     * 获取我邀请的用户列表
     */
    public function invited_users(): HasMany
    {
        return $this->hasMany(Invitation::class, 'inviter_id');
    }

    /**
     * 获取邀请我的人（通过邀请关系，一个用户只能有一个邀请人）
     */
    public function invitation(): HasOne
    {
        return $this->hasOne(Invitation::class, 'invitee_id');
    }

    /**
     * 根据邀请码查找用户
     */
    public static function findByInviteCode(string $inviteCode): ?User
    {
        return static::where('invite_code', $inviteCode)->first();
    }

    /**
     * 获取用户统计数据
     */
    public function statistics(): HasOne
    {
        return $this->hasOne(UserStatistic::class);
    }

    /**
     * 获取用户VIP信息
     */
    public function vip(): HasOne
    {
        return $this->hasOne(UserVip::class);
    }

    /**
     * 获取用户扩展统计属性
     */
    public function statistic_attributes(): HasMany
    {
        return $this->hasMany(UserStatisticAttribute::class);
    }

    /**
     * 获取用户选择的展示货币列表
     */
    public function getDisplayCurrencies(): array
    {
        return $this->display_currencies ?? [];
    }

    /**
     * 设置用户选择的展示货币列表
     */
    public function setDisplayCurrencies(array $currencies): void
    {
        $this->display_currencies = $currencies;
        $this->save();
    }

    /**
     * 获取用户基准币种
     */
    public function getBaseCurrency(): ?string
    {
        return $this->base_currency ?? 'USD';
    }

    /**
     * 设置用户基准币种
     */
    public function setBaseCurrency(string $currency): void
    {
        $this->base_currency = strtoupper($currency);
        $this->save();
    }

    /**
     * 获取用户当前使用的币种
     */
    public function getCurrentCurrency(): ?string
    {
        return $this->current_currency ?? $this->base_currency;
    }

    /**
     * 设置用户当前使用的币种
     */
    public function setCurrentCurrency(string $currency): void
    {
        $this->current_currency = strtoupper($currency);
        $this->save();
    }

    /**
     * 获取用户的 meta 数据
     */
    public function metas(): HasMany
    {
        return $this->hasMany(UserMeta::class);
    }

    /**
     * 添加用户的 meta 值
     */
    public function addMeta(string $key, string $value): UserMeta
    {
        return UserMeta::addValue($this->id, $key, $value);
    }

    /**
     * 获取用户某个 key 的最新 meta 值
     */
    public function getLatestMeta(string $key): ?string
    {
        return UserMeta::getLatest($this->id, $key);
    }

    /**
     * 获取用户某个 key 的所有 meta 值
     */
    public function getAllMeta(string $key): array
    {
        return UserMeta::getAll($this->id, $key);
    }

    /**
     * 获取用户的标签
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'user_tags')
            ->withPivot('created_at');
    }

    /**
     * 检查用户是否有指定标签
     */
    public function hasTag(string $tagName): bool
    {
        return $this->tags()->where('name', $tagName)->exists();
    }

    /**
     * 检查用户是否有指定标签（通过ID）
     */
    public function hasTagById(int $tagId): bool
    {
        return $this->tags()->where('tags.id', $tagId)->exists();
    }

    /**
     * 添加标签
     */
    public function addTag(int $tagId, ?string $value = null, ?string $reason = null): void
    {
        if (!$this->hasTagById($tagId)) {
            $this->tags()->attach($tagId);
        }

        // 记录打标签日志
        if ($value !== null) {
            UserTagLog::log($this->id, $tagId, $value, $reason);
        }
    }

    /**
     * 获取用户的打标签记录
     */
    public function tagLogs(): HasMany
    {
        return $this->hasMany(UserTagLog::class);
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the user's favorite games.
     */
    public function favoriteGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'user_game_favorites')
            ->withTimestamps()
            ->orderBy('user_game_favorites.created_at', 'desc');
    }

    /**
     * 移除标签
     */
    public function removeTag(int $tagId): void
    {
        $this->tags()->detach($tagId);
    }

    /**
     * 同步标签（替换所有标签）
     */
    public function syncTags(array $tagIds): void
    {
        $this->tags()->sync($tagIds);
    }
}
