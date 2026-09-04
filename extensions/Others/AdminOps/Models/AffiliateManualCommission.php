<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One manually-entered commission (the reference's "Add Manual Commission Entry",
 * Commissions History tab). The money owed moves the same way a real referral's does —
 * it counts toward the affiliate's earned total and their Available to Withdraw balance
 * — but nothing paid it, so it lives in AdminOps's own ledger rather than a change to the
 * Affiliates extension's `ext_affiliates`/`ext_affiliate_orders` tables. Same pattern as
 * {@see AffiliateWithdrawal}.
 */
class AffiliateManualCommission extends Model
{
    protected $table = 'ext_affiliate_manual_commissions';

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2'];
}
