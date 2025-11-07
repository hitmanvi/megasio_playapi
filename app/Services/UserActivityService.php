<?php

namespace App\Services;

use App\Models\UserActivity;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class UserActivityService
{
    /**
     * Create a new user activity record.
     *
     * @param int $userId
     * @param string $activityType
     * @param string $description
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param array|null $metadata
     * @return UserActivity
     */
    public function createActivity(
        int $userId,
        string $activityType,
        string $description,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): UserActivity {
        return UserActivity::create([
            'user_id' => $userId,
            'activity_type' => $activityType,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get user activities with filters and pagination.
     *
     * @param int $userId
     * @param array $filters Supported filters: activity_type, period (24h, 7d, 30d)
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserActivitiesPaginated(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = UserActivity::query()
            ->where('user_id', $userId)
            ->with(['user'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['activity_type'])) {
            $query->ofType($filters['activity_type']);
        }

        // Apply time period filter (24h, 7d, 30d)
        if (isset($filters['period'])) {
            $endDate = Carbon::now();
            $startDate = match($filters['period']) {
                '24h' => $endDate->copy()->subDay(),
                '7d' => $endDate->copy()->subDays(7),
                '30d' => $endDate->copy()->subDays(30),
                default => null,
            };
            
            if ($startDate) {
                $query->inDateRange($startDate, $endDate);
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * Format activity for API response.
     *
     * @param UserActivity $activity
     * @param bool $includeDetails Include additional details
     * @return array
     */
    public function formatActivityForResponse(UserActivity $activity, bool $includeDetails = false): array
    {
        $data = [
            'id' => $activity->id,
            'activity_type' => $activity->activity_type,
            'description' => $activity->description,
            'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
        ];

        if ($includeDetails) {
            $data['ip_address'] = $activity->ip_address;
            $data['user_agent'] = $activity->user_agent;
            $data['metadata'] = $activity->metadata;
            $data['updated_at'] = $activity->updated_at->format('Y-m-d H:i:s');
        }

        return $data;
    }
}

