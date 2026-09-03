<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Models;

use App\Models\InvoiceTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The free-text Description a transaction was given when it was added by hand, matching
 * the reference's own field — one per transaction, never edited after the fact.
 */
class TransactionNote extends Model
{
    protected $table = 'ext_transaction_notes';

    protected $guarded = [];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InvoiceTransaction::class, 'transaction_id');
    }
}
