<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    // 消息类型常量
    const TYPE_SYSTEM = 'system';  // 系统消息
    const TYPE_USER = 'user';      // 用户消息

    // 消息分类常量
    const CATEGORY_REGISTER = 'register';                    // 注册欢迎
    const CATEGORY_DEPOSIT_SUCCESS = 'deposit_success';      // 充值成功
    const CATEGORY_WITHDRAW_SUCCESS = 'withdraw_success';    // 提现成功
    const CATEGORY_VIP_LEVEL_UP = 'vip_level_up';            // VIP等级提升
    const CATEGORY_BONUS_TASK = 'bonus_task';                // 得到奖励任务
    const CATEGORY_BONUS_TASK_COMPLETED = 'bonus_task_completed'; // 奖励任务完成
    const CATEGORY_INVITATION_REWARD = 'invitation_reward';  // 邀请奖励
    const CATEGORY_SYSTEM_ANNOUNCEMENT = 'system_announcement'; // 系统公告

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'category',
        'title',
        'content',
        'data',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter system notifications.
     */
    public function scopeSystem($query)
    {
        return $query->where('type', self::TYPE_SYSTEM)
                     ->whereNull('user_id');
    }

    /**
     * Scope to filter user notifications.
     */
    public function scopeUserNotifications($query, ?int $userId = null)
    {
        $query = $query->where('type', self::TYPE_USER);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        return $query;
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to filter read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope to order by created_at desc.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): bool
    {
        if ($this->isRead()) {
            return false;
        }

        $this->read_at = now();
        return $this->save();
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): bool
    {
        if (!$this->isRead()) {
            return false;
        }

        $this->read_at = null;
        return $this->save();
    }
}
