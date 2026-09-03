<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One recorded affiliate payout (issue #6). The money moves outside the panel; this row
 * is the panel's memory of it — who was paid, how much, in what, by whom.
 */
class AffiliateWithdrawal extends Model
{
    protected $table = 'ext_affiliate_withdrawals';

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2'];
}
