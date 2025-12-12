<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kyc extends Model
{
    /**
     * KYC status constants.
     * 
     * 流程：
     * 1. pending -> approved/rejected (初审)
     * 2. approved -> selfie_pending (提交自拍)
     * 3. selfie_pending -> selfie_approved/selfie_rejected (自拍审核)
     */
    const STATUS_PENDING = 'pending';                 // 初审待审核
    const STATUS_APPROVED = 'approved';               // 初审通过（可提交自拍）
    const STATUS_REJECTED = 'rejected';               // 初审拒绝
    const STATUS_SELFIE_PENDING = 'selfie_pending';   // 自拍待审核
    const STATUS_SELFIE_APPROVED = 'selfie_approved'; // 自拍通过（完成）
    const STATUS_SELFIE_REJECTED = 'selfie_rejected'; // 自拍拒绝

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'birthdate',
        'document_front',
        'document_back',
        'document_number',
        'selfie',
        'status',
        'reject_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'user_id',
    ];

    /**
     * Get the user that owns the KYC.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if KYC is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if KYC is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if KYC is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if selfie is pending.
     */
    public function isSelfiePending(): bool
    {
        return $this->status === self::STATUS_SELFIE_PENDING;
    }

    /**
     * Check if selfie is approved (full KYC complete).
     */
    public function isSelfieApproved(): bool
    {
        return $this->status === self::STATUS_SELFIE_APPROVED;
    }

    /**
     * Check if selfie is rejected.
     */
    public function isSelfieRejected(): bool
    {
        return $this->status === self::STATUS_SELFIE_REJECTED;
    }

    /**
     * Check if can submit selfie (initial KYC approved).
     */
    public function canSubmitSelfie(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_SELFIE_REJECTED]);
    }

    /**
     * Check if full KYC is complete.
     */
    public function isFullyVerified(): bool
    {
        return $this->status === self::STATUS_SELFIE_APPROVED;
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending KYCs.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter approved KYCs.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to filter rejected KYCs.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}

