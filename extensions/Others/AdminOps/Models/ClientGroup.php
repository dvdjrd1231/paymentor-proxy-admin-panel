<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/** A client group as an Eloquent model. */
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
