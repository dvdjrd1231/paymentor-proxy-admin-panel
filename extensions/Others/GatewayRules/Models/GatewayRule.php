<?php

namespace Paymenter\Extensions\Others\GatewayRules\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A gateway-availability rule.
 *
 * Decides whether a payment gateway is offered at checkout, scoped by customer
 * country, product, product group (category), customer type, currency and
 * invoice/cart amount. First active rule (lowest `priority`) that matches wins:
 * its `mode` (allow|deny) is the decision. Enforced server-side.
 */
class GatewayRule extends Model
{
    protected $table = 'gateway_rules';

    protected $guarded = [];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'active' => 'boolean',
        'priority' => 'integer',
        'product_id' => 'integer',
        'category_id' => 'integer',
    ];
}
