<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Balance extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'currency',
        'available',
        'frozen',
        'version',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'available' => 'decimal:8',
        'frozen' => 'decimal:8',
        'version' => 'integer',
    ];

    /**
     * List of fields to be hidden in array/json representations
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'user_id',
        'created_at',
        'updated_at',
        'version',
    ];

    /**
     * Get the user that owns the balance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


}
