<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteLink extends Model
{
    protected $fillable = [
        'key',
        'url',
        'deletable',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'deletable' => 'boolean',
            'enabled' => 'boolean',
        ];
    }
}
