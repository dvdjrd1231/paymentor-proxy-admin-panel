<?php

namespace Paymenter\Extensions\Others\BillableItems\Models;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ad-hoc charge, waiting for an invoice or already on one.
 *
 * @property string|null $recur_every null is the reference's "Never"
 */
class BillableItem extends Model
{
    protected $table = 'ext_billable_items';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'decimal:2',
        'next_due_at' => 'date',
        'invoiced_at' => 'datetime',
    ];

    /**
     * The reference's **Invoice Action**, less the two that only mean something inside its
     * own due-date model.
     */
    public const ACTION_NEXT_INVOICE = 'next_invoice';

    public const ACTION_IMMEDIATELY = 'immediately';

    public const ACTION_HOLD = 'hold';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isInvoiced(): bool
    {
        return $this->invoiced_at !== null;
    }

    /** What the line is worth: the reference keeps hours and rate apart, and so does this. */
    public function total(): float
    {
        return round((float) $this->amount * (float) $this->quantity, 2);
    }

    /** How the line reads on the invoice — the quantity only when there is more than one. */
    public function line(): string
    {
        return (float) $this->quantity == 1.0
            ? $this->description
            : $this->description . ' (' . rtrim(rtrim(number_format((float) $this->quantity, 2), '0'), '.') . ' × '
                . number_format((float) $this->amount, 2) . ')';
    }
}
