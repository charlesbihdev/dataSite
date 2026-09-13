<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $amount
 * @property string $status
 * @property string|null $method
 * @property string|null $destination
 * @property string|null $reference
 * @property string|null $admin_notes
 * @property Carbon|null $processed_at
 */
#[Fillable(['amount', 'method', 'destination', 'status', 'reference', 'admin_notes', 'processed_at'])]
class Withdrawal extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function earner(): MorphTo
    {
        return $this->morphTo();
    }
}
