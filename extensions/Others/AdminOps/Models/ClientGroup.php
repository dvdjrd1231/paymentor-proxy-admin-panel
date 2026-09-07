<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A client group as an Eloquent model.
 *
 * The screen and the sweep both work in plain queries, so this exists for one reason: an
 * invoice's discount line has to be findable again — to be rebuilt when the invoice
 * changes — and the only free text field on an invoice item is the description, which the
 * customer reads. Marking the line with a `[group-discount]` prefix, the way the payment
 * fee does, would put that prefix on the customer's invoice.
 *
 * `reference_type` / `reference_id` are the right place for a marker: invisible on the
 * invoice, exact to match on, and already how a line points at the service it bills. That
 * only works if the type resolves to a real model, hence this class.
 */
class ClientGroup extends Model
{
    protected $table = 'ext_client_groups';

    protected $fillable = ['name', 'colour', 'discount_percent', 'suspend_exempt', 'separate_invoices'];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'suspend_exempt' => 'boolean',
        'separate_invoices' => 'boolean',
    ];
}
