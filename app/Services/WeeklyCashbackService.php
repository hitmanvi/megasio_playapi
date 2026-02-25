<?php

namespace App\Services;

use Carbon\Carbon;

class WeeklyCashbackService
{
    /**
     * 将日期转换为 period（ISO 年*100+周数）
     */
    public function dateToPeriod(Carbon|string $date): int
    {
        $dt = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $dt->isoWeekYear() * 100 + $dt->isoWeek();
    }
}
