<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Redis;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class UserRecentGameService
{
    /**
     * Redis key 前缀
     */
    const CACHE_PREFIX = 'user_recent_games:';
    
    /**
     * 缓存过期时间（秒）- 7天
     */
    const CACHE_TTL = 604800;
    
    /**
     * 每个用户最多缓存的游戏数量
     */
    const MAX_GAMES_PER_USER = 100;

    /**
     * 获取用户的 Redis key
     */
    private function getCacheKey(int $userId): string
    {
        return self::CACHE_PREFIX . $userId;
    }

    /**
     * 记录用户游玩游戏
     */
    public function recordPlay(int $userId, int $gameId, float $multiplier = 0): void
    {
        $timestamp = time();
        $cacheKey = $this->getCacheKey($userId);
        $dataKey = $cacheKey . ':data';
        
        // 获取现有数据
        $existingData = Redis::hget($dataKey, $gameId);
        $data = $existingData ? json_decode($existingData, true) : [
            'play_count' => 0,
            'max_multiplier' => 0,
        ];
        
        // 更新数据
        $data['play_count'] = ($data['play_count'] ?? 0) + 1;
        if ($multiplier > ($data['max_multiplier'] ?? 0)) {
            $data['max_multiplier'] = $multiplier;
        }
        $data['last_played_at'] = $timestamp;
        
        // 使用 pipeline 批量操作
        Redis::pipeline(function ($pipe) use ($cacheKey, $dataKey, $gameId, $timestamp, $data) {
            // 更新 Sorted Set（用于按时间排序）
            $pipe->zadd($cacheKey . ':recent', $timestamp, $gameId);
            // 更新 Sorted Set（用于按游玩次数排序）
            $pipe->zadd($cacheKey . ':play_count', $data['play_count'], $gameId);
            // 更新 Sorted Set（用于按最大倍数排序）
            $pipe->zadd($cacheKey . ':max_multiplier', $data['max_multiplier'], $gameId);
            // 更新 Hash（存储详细数据）
            $pipe->hset($dataKey, $gameId, json_encode($data));
            
            // 设置过期时间
            $pipe->expire($cacheKey . ':recent', self::CACHE_TTL);
            $pipe->expire($cacheKey . ':play_count', self::CACHE_TTL);
            $pipe->expire($cacheKey . ':max_multiplier', self::CACHE_TTL);
            $pipe->expire($dataKey, self::CACHE_TTL);
        });
        
        // 限制缓存数量
        $this->trimCache($userId);
    }

    /**
     * 限制缓存数量，移除最旧的记录
     */
    private function trimCache(int $userId): void
    {
        $cacheKey = $this->getCacheKey($userId);
        
        $count = Redis::zcard($cacheKey . ':recent');
        if ($count > self::MAX_GAMES_PER_USER) {
            $removeCount = $count - self::MAX_GAMES_PER_USER;
            $gameIdsToRemove = Redis::zrange($cacheKey . ':recent', 0, $removeCount - 1);
            
            if (!empty($gameIdsToRemove)) {
                Redis::pipeline(function ($pipe) use ($cacheKey, $gameIdsToRemove) {
                    foreach ($gameIdsToRemove as $gameId) {
                        $pipe->zrem($cacheKey . ':recent', $gameId);
                        $pipe->zrem($cacheKey . ':play_count', $gameId);
                        $pipe->zrem($cacheKey . ':max_multiplier', $gameId);
                        $pipe->hdel($cacheKey . ':data', $gameId);
                    }
                });
            }
        }
    }

    /**
     * 获取用户最近游玩的游戏
     */
    public function getRecentGames(int $userId, string $sort = 'recent', int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = $this->getCacheKey($userId);
        $dataKey = $cacheKey . ':data';
        
        // 根据排序方式选择对应的 Sorted Set
        $sortKey = match ($sort) {
            'play_count' => $cacheKey . ':play_count',
            'max_multiplier' => $cacheKey . ':max_multiplier',
            default => $cacheKey . ':recent',
        };
        
        // 获取总数
        $total = Redis::zcard($sortKey) ?: 0;
        
        if ($total === 0) {
            return new LengthAwarePaginator(collect([]), 0, $perPage, $page);
        }
        
        // 计算分页偏移
        $offset = ($page - 1) * $perPage;
        
        // 获取分页后的游戏ID（倒序）
        $gameIds = Redis::zrevrange($sortKey, $offset, $offset + $perPage - 1);
        
        if (empty($gameIds)) {
            return new LengthAwarePaginator(collect([]), $total, $perPage, $page);
        }
        
        // 获取详细数据
        $dataList = Redis::hmget($dataKey, is_array($gameIds) ? $gameIds : [$gameIds]);
        
        // 获取游戏信息
        $games = Game::with(['brand', 'category', 'themes'])
            ->whereIn('id', $gameIds)
            ->enabled()
            ->get()
            ->keyBy('id');
        
        // 组装结果
        $result = collect($gameIds)->map(function ($gameId, $index) use ($games, $dataList) {
            $game = $games->get($gameId);
            if (!$game) {
                return null;
            }
            
            $data = json_decode($dataList[$index] ?? '{}', true);
            
            return [
                'game' => $game,
                'play_count' => $data['play_count'] ?? 0,
                'max_multiplier' => (float) ($data['max_multiplier'] ?? 0),
                'last_played_at' => isset($data['last_played_at']) 
                    ? Carbon::createFromTimestamp($data['last_played_at']) 
                    : null,
            ];
        })->filter()->values();
        
        return new LengthAwarePaginator($result, $total, $perPage, $page);
    }

    /**
     * 批量设置用户游玩记录（用于测试/数据迁移）
     */
    public function batchSet(int $userId, array $records): void
    {
        $cacheKey = $this->getCacheKey($userId);
        $dataKey = $cacheKey . ':data';
        
        Redis::pipeline(function ($pipe) use ($cacheKey, $dataKey, $records) {
            foreach ($records as $record) {
                $gameId = $record['game_id'];
                $timestamp = $record['last_played_at'] instanceof Carbon 
                    ? $record['last_played_at']->timestamp 
                    : $record['last_played_at'];
                
                $data = [
                    'play_count' => $record['play_count'],
                    'max_multiplier' => $record['max_multiplier'],
                    'last_played_at' => $timestamp,
                ];
                
                $pipe->zadd($cacheKey . ':recent', $timestamp, $gameId);
                $pipe->zadd($cacheKey . ':play_count', $record['play_count'], $gameId);
                $pipe->zadd($cacheKey . ':max_multiplier', $record['max_multiplier'], $gameId);
                $pipe->hset($dataKey, $gameId, json_encode($data));
            }
            
            $pipe->expire($cacheKey . ':recent', self::CACHE_TTL);
            $pipe->expire($cacheKey . ':play_count', self::CACHE_TTL);
            $pipe->expire($cacheKey . ':max_multiplier', self::CACHE_TTL);
            $pipe->expire($dataKey, self::CACHE_TTL);
        });
    }

    /**
     * 清除用户缓存
     */
    public function clearCache(int $userId): void
    {
        $cacheKey = $this->getCacheKey($userId);
        
        Redis::del([
            $cacheKey . ':recent',
            $cacheKey . ':play_count',
            $cacheKey . ':max_multiplier',
            $cacheKey . ':data',
        ]);
    }
}
