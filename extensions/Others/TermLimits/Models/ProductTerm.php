<?php

namespace Paymenter\Extensions\Others\TermLimits\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product's **Auto Terminate/Fixed Term** setting — the reference's field, per product.
 *
 * @property int $days 0 = off, as the reference means it
 */
class ProductTerm extends Model
{
    protected $table = 'ext_term_limit_products';

    protected $guarded = [];

    protected $casts = [
        'days' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The contracted length in hours, or null when the field is off. */
    public function hours(): ?int
    {
        return $this->days > 0 ? $this->days * 24 : null;
    }
}
