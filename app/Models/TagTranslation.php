<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagTranslation extends Model
{
    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
