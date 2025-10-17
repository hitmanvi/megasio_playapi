<?php

namespace App\Models;

class Tag extends Model
{
    public function translations()
    {
        return $this->hasMany(TagTranslation::class);
    }
}
