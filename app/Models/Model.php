<?php

namespace App\Models;

use App\Traits\ModelTrait;
use DateTimeInterface;

class Model extends \Illuminate\Database\Eloquent\Model
{
    use ModelTrait;

    /**
     * 为日期字段格式化输出
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
