<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerIoCampaignPromotionCode extends Model
{
    protected $table = 'customer_io_campaign_promotion_codes';

    protected $fillable = [
        'campaign_id',
        'promotion_code_id',
        'remark',
    ];

    public function promotionCode(): BelongsTo
    {
        return $this->belongsTo(PromotionCode::class);
    }
}
