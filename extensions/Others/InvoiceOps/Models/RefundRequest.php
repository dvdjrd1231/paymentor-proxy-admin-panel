<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Models;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request for money back, and the answer to it.
 *
 * @property string $status pending | approved | refused
 */
class RefundRequest extends Model
{
    protected $table = 'ext_refund_requests';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'decided_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REFUSED = 'refused';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(InvoiceRefund::class, 'refund_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** What was asked for: the stated amount, or the whole invoice when none was given. */
    public function requested(): float
    {
        return $this->amount !== null
            ? (float) $this->amount
            : (float) ($this->invoice?->total ?? 0);
    }
}
