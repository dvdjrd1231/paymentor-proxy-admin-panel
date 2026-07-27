<?php

namespace Paymenter\Extensions\Others\TicketTools\Models;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff-only internal note attached to a ticket. Never rendered in the client
 * theme, so it is never visible to customers.
 */
class TicketNote extends Model
{
    protected $table = 'ticket_notes';

    protected $guarded = [];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
