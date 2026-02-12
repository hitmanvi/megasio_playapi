<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class UserWagerService
{
    /**
     * Redis key 前缀
     */
    protected const REDIS_PREFIX = 'user:wager:';

    /**
     * 增加用户的 wager
     *
     * @param int $userId
     * @param float $amount
     * @param string $date 日期，格式：Y-m-d，默认为今天
     * @return void
     */
    public function addWager(int $userId, float $amount, ?string $date = null): void
    {
        if ($date === null) {
            $date = Carbon::today()->format('Y-m-d');
        }

        $key = $this->getKey($userId, $date);
        Redis::incrbyfloat($key, $amount);
        
        // 设置过期时间为7天（确保数据不会永久保存）
        Redis::expire($key, 7 * 24 * 60 * 60);
    }

    /**
     * 删除用户的 wager（通常在生成奖励后清理）
     *
     * @param int $userId
     * @param string $date 日期，格式：Y-m-d
     * @return void
     */
    public function deleteWager(int $userId, string $date): void
    {
        $key = $this->getKey($userId, $date);
        Redis::del($key);
    }

    /**
     * 获取指定日期的所有用户的 wager 数据
     *
     * @param string $date 日期，格式：Y-m-d
     * @return array ['user_id' => wager]
     */
    public function getAllWagersByDate(string $date): array
    {
        // 直接用 KEYS 获取所有匹配的 wager key
        $pattern = self::REDIS_PREFIX . '*:' . $date;
        $result = [];
        $keys = Redis::keys($pattern);

        foreach ($keys as $key) {
            // 从 key 中提取 user_id
            // key 格式：user:wager:{userId}:{date}
            $parts = explode(':', $key);
            if (count($parts) >= 3) {
                $userId = (int) $parts[2];
                $wager = Redis::get($key);
                if ($wager && $wager > 0) {
                    $result[$userId] = (float) $wager;
                }
            }
        }

        return $result;
    }

    /**
     * 获取 Redis key
     *
     * @param int $userId
     * @param string $date
     * @return string
     */
    protected function getKey(int $userId, string $date): string
    {
        return self::REDIS_PREFIX . $userId . ':' . $date;
    }
}
