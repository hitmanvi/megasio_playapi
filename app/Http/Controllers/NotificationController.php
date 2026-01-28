<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Enums\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * 获取通知列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->input('type'); // system 或 user
        $category = $request->input('category');
        $read = $request->input('read'); // true/false/null
        $lastId = $request->input('last_id'); // 游标分页的最后一个ID
        $limit = min((int) $request->input('limit', 20), 100); // 限制最大100条

        $query = Notification::query()
            ->orderBy('id', 'desc'); // 按ID降序排列

        // 如果是系统消息，查询所有用户都能看到的系统消息
        if ($type === 'system') {
            $query->where('type', Notification::TYPE_SYSTEM)->whereNull('user_id');
        } else {
            // 默认查询用户个人消息
            $query->where('type', Notification::TYPE_USER)->where('user_id', $user->id);
        }

        // 按分类筛选
        if ($category) {
            $query->where('category', $category);
        }

        // 按阅读状态筛选
        if ($read === 'true' || $read === '1') {
            $query->whereNotNull('read_at');
        } elseif ($read === 'false' || $read === '0') {
            $query->whereNull('read_at');
        }

        // 游标分页：如果提供了 last_id，查询 ID 小于 last_id 的记录
        if ($lastId) {
            $query->where('id', '<', $lastId);
        }

        // 获取 limit + 1 条记录，用于判断是否有更多数据
        $notifications = $query->limit($limit + 1)->get();

        // 判断是否有更多数据
        $hasMore = $notifications->count() > $limit;
        
        // 如果有多余的记录，移除最后一条
        if ($hasMore) {
            $notifications = $notifications->take($limit);
        }

        // 格式化返回数据
        $items = $notifications->map(function ($notification) {
            return $this->formatNotification($notification);
        });

        // 获取最后一条记录的ID
        $nextLastId = $notifications->isNotEmpty() ? $notifications->last()->id : null;

        return $this->responseItem([
            'items' => $items->values(),
            'has_more' => $hasMore,
            'last_id' => $nextLastId,
        ]);
    }

    /**
     * 获取未读通知数量
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $userUnreadCount = Notification::where('type', Notification::TYPE_USER)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
        $systemUnreadCount = Notification::where('type', Notification::TYPE_SYSTEM)
            ->whereNull('user_id')
            ->whereNull('read_at')
            ->count();

        return $this->responseItem([
            'user_unread_count' => $userUnreadCount,
            'system_unread_count' => $systemUnreadCount,
            'total_unread_count' => $userUnreadCount + $systemUnreadCount,
        ]);
    }

    /**
     * 获取单个通知详情
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $notification = Notification::find($id);

        if (!$notification) {
            return $this->error(ErrorCode::NOT_FOUND, 'Notification not found');
        }

        // 检查权限：用户只能查看自己的消息或系统消息
        if ($notification->type === Notification::TYPE_USER && $notification->user_id !== $user->id) {
            return $this->error(ErrorCode::FORBIDDEN, 'You do not have permission to view this notification');
        }

        // 标记为已读
        if (!$notification->isRead()) {
            $notification->markAsRead();
        }

        return $this->responseItem($this->formatNotification($notification));
    }

    /**
     * 标记通知为已读
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $notification = Notification::find($id);

        if (!$notification) {
            return $this->error(ErrorCode::NOT_FOUND, 'Notification not found');
        }

        // 检查权限
        if ($notification->type === Notification::TYPE_USER && $notification->user_id !== $user->id) {
            return $this->error(ErrorCode::FORBIDDEN, 'You do not have permission to mark this notification as read');
        }

        $notification->markAsRead();

        return $this->responseItem($this->formatNotification($notification));
    }

    /**
     * 批量标记为已读
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->input('type'); // system 或 user

        $query = Notification::query();

        if ($type === 'system') {
            $query->where('type', Notification::TYPE_SYSTEM)
                  ->whereNull('user_id')
                  ->whereNull('read_at');
        } else {
            $query->where('type', Notification::TYPE_USER)
                  ->where('user_id', $user->id)
                  ->whereNull('read_at');
        }

        $count = $query->update(['read_at' => now()]);

        return $this->responseItem([
            'message' => "Marked {$count} notifications as read",
            'count' => $count,
        ]);
    }

    /**
     * 格式化通知数据
     * 
     * @param Notification $notification
     * @return array
     */
    protected function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'category' => $notification->category,
            'title' => $notification->title,
            'content' => $notification->content,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'is_read' => $notification->isRead(),
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
