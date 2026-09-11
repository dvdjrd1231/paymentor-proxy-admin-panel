<?php

namespace Paymenter\Extensions\Others\GatewayRules\Models;

use Illuminate\Database\Eloquent\Model;

/** A gateway-availability rule. */
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
