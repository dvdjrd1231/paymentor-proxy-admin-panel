<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Models;

use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One refund, recorded against an invoice.
 *
 * Append-only, like a term extension: a refund is a fact about money that has moved, and
 * correcting one is another row, not an edit.
 */
class InvoiceRefund extends Model
{
    protected $table = 'ext_invoice_refunds';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'reversed_service' => 'boolean',
    ];

    public const METHOD_GATEWAY = 'gateway';

    public const METHOD_OFFLINE = 'offline';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InvoiceTransaction::class, 'transaction_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
