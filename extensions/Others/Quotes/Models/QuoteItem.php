<?php

namespace Paymenter\Extensions\Others\Quotes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a quote. */
class QuoteItem extends Model
{
    protected $table = 'ext_quote_items';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'sort' => 'integer',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function total(): float
    {
        // The reference's per-line discount, applied here so every total in the system —
        // quote, PDF, the invoice it becomes — says the same number.
        return round((float) $this->price * (float) $this->quantity
            * (1 - (float) ($this->discount ?? 0) / 100), 2);
    }
}
