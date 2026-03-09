<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempData extends Model
{
    protected $table = 'temp_data';

    protected $fillable = ['payload'];

    protected $casts = [
        'payload' => 'array',
    ];
}
