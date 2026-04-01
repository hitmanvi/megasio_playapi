<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteLink extends Model
{
    protected $fillable = [
        'key',
        'url',
        'deletable',
    ];

    protected function casts(): array
    {
        return [
            'deletable' => 'boolean',
        ];
    }
}
