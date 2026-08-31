<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A category of the reference's Predefined Replies. The replies themselves are
 * TicketTools' canned responses, whose `department` column carries the category name.
 */
class PredefinedReplyCategory extends Model
{
    protected $table = 'ext_predefined_reply_categories';

    protected $guarded = [];
}
