<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Models;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money recorded with no invoice to apply it to — the reference's own Add Transaction
 * allows this; see the migration for why it lives here rather than as a nullable column
 * on core's `invoice_transactions`.
 */
class UnappliedTransaction extends Model
{
    protected $table = 'ext_unapplied_transactions';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
