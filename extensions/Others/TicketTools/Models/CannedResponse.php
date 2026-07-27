<?php

namespace Paymenter\Extensions\Others\TicketTools\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A canned (quick) reply staff can reuse when answering tickets.
 */
class CannedResponse extends Model
{
    protected $table = 'canned_responses';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];
}
