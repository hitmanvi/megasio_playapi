<?php

/**
 * User: hitman
 * Date: 08/08/2017
 * Time: 3:45 PM
 */

namespace App\Traits;

trait ModelTrait
{
    public function scopeOfProperty($query, $key, $value)
    {
        if (trim($value) == '') {
            return $query;
        }

        return $query->where($key, $value);
    }

    public function scopeOfPrefix($query, $key, $value)
    {
        if (trim($value) == '') {
            return $query;
        }

        return $query->where($key, 'like', "$value%");
    }

    public function scopeOfPostfix($query, $key, $value)
    {
        if (trim($value) == '') {
            return $query;
        }

        return $query->where($key, 'like', "%$value");
    }

    public function scopeOfKeyword($query, $key, $value)
    {
        if (trim($value) == '') return $query;

        return $query->where($key, 'like', "%$value%");
    }

    public function scopeOfOperator($query, $key, $operator, $value)
    {
        if (trim($value) == '') {
            return $query;
        }

        return $query->where($key, $operator, $value);
    }
}
