<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait CommandLockTrait
{
    protected $startTime;

    public function lock()
    {
        $this->startTime = microtime(true);
        $lock = Cache::get("commands:lock:$this->signature");

        if(!$lock) {
            Cache::put("commands:lock:$this->signature", 1);
            Log::error("{$this->signature} lock success, time: $this->startTime");
            return true;
        }
        if(Cache::get("lock:failed:{$this->signature}", 0) > 10) {
            Cache::forget("commands:lock:$this->signature");
            Cache::forget("lock:failed:{$this->signature}");
            Log::error("reset lock {$this->signature}");
        } else {
            Cache::increment("lock:failed:{$this->signature}");
        }
        Log::error("$this->signature fetch lock failed, key: commands:lock:$this->signature");
        return false;
    }

    public function unlock()
    {
        $time =  microtime(true);
        $duration = $time - $this->startTime;
        Log::error("{$this->signature} unlock success. duration: $duration");
        Cache::forget("commands:lock:$this->signature");
    }
}
