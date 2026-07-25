<?php

namespace Paymenter\Extensions\Others\PaymentFees\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A payment-method fee rule.
 *
 * Fees are charged per selected gateway and can be fixed, percentage, or both,
 * scoped by country / currency / product / customer type / invoice-amount range.
 * The first active rule (lowest `priority`) whose scope matches an invoice wins.
 * All calculation happens server-side in Support\FeeCalculator.
 */
class PaymentFeeRule extends Model
{
    protected $table = 'payment_fee_rules';

    protected $guarded = [];

    protected $casts = [
        'fixed_amount' => 'decimal:2',
        'percent_amount' => 'decimal:4',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'active' => 'boolean',
        'priority' => 'integer',
        'product_id' => 'integer',
    ];
}
