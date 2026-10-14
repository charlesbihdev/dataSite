<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $phone
 * @property string|null $network
 * @property int|null $agent_id
 * @property string|null $source_label
 * @property string $status
 * @property string|null $failure_message
 * @property int $check_count
 * @property CarbonInterface|null $submitted_at
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $last_checked_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property Agent|null $agent
 */
#[Fillable([
    'phone',
    'network',
    'agent_id',
    'source_label',
    'status',
    'failure_message',
    'check_count',
    'submitted_at',
    'approved_at',
    'last_checked_at',
])]
class UnapprovedBeneficiary extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';

    protected function casts(): array
    {
        return [
            'check_count' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}

