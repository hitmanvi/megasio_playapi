<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
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
        'status',
        'ban_reason',
        'invite_code',
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
     * 获取用户扩展统计属性
     */
    public function statistic_attributes(): HasMany
    {
        return $this->hasMany(UserStatisticAttribute::class);
    }
}
