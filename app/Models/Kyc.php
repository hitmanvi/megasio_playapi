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
     * 2. approved -> advanced_pending (提交高级认证)
     * 3. advanced_pending -> advanced_approved/advanced_rejected (高级认证审核)
     */
    const STATUS_PENDING = 'pending';                     // 初审待审核
    const STATUS_APPROVED = 'approved';                   // 初审通过（可提交高级认证）
    const STATUS_REJECTED = 'rejected';                   // 初审拒绝
    const STATUS_ADVANCED_PENDING = 'advanced_pending';   // 高级认证待审核
    const STATUS_ADVANCED_APPROVED = 'advanced_approved'; // 高级认证通过（完成）
    const STATUS_ADVANCED_REJECTED = 'advanced_rejected'; // 高级认证拒绝

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
     * Check if advanced verification is pending.
     */
    public function isAdvancedPending(): bool
    {
        return $this->status === self::STATUS_ADVANCED_PENDING;
    }

    /**
     * Check if advanced verification is approved (full KYC complete).
     */
    public function isAdvancedApproved(): bool
    {
        return $this->status === self::STATUS_ADVANCED_APPROVED;
    }

    /**
     * Check if advanced verification is rejected.
     */
    public function isAdvancedRejected(): bool
    {
        return $this->status === self::STATUS_ADVANCED_REJECTED;
    }

    /**
     * Check if can submit advanced verification (initial KYC approved).
     */
    public function canSubmitAdvanced(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_ADVANCED_REJECTED]);
    }

    /**
     * Check if full KYC is complete.
     */
    public function isFullyVerified(): bool
    {
        return $this->status === self::STATUS_ADVANCED_APPROVED;
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

