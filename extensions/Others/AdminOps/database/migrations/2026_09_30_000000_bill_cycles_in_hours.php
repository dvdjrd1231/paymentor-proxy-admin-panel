<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put Daily, Weekly and Monthly on an hour count instead of a calendar one.
 *
 * Leandro, on #10: "I would like the billing cycles to be based on hours because sometimes,
 * in WHMCS, a service with a daily or weekly billing cycle is terminated either before or
 * after the exact expected time. When the cycle is based on hours, it will always be
 * terminated at the correct time."
 *
 * A calendar day is not always 24 hours — it is 23 or 25 across a daylight-saving change —
 * and a calendar month is anything from 28 to 31 days. Counting hours makes the end of a
 * term exact. The lengths are his: 25 hours for Daily, 169 for Weekly (each a full period
 * plus an hour of grace) and 30 × 24 for Monthly.
 *
 * Prices are attached to a plan by id, so re-shaping a plan keeps them. Terms already
 * running are unaffected: {@see \Paymenter\Extensions\Others\TermLimits\Models\ServiceTerm}
 * stores the hours that were sold, precisely so a later re-timing cannot move the end of a
 * term somebody has already paid for.
 */
return new class extends Migration
{
    /** The shapes being replaced, and what each becomes. */
    private const MOVES = [
        ['from' => ['type' => 'one-time', 'billing_period' => 1, 'billing_unit' => 'day'],
            'to' => ['billing_period' => 25, 'billing_unit' => 'hour']],
        ['from' => ['type' => 'one-time', 'billing_period' => 1, 'billing_unit' => 'week'],
            'to' => ['billing_period' => 169, 'billing_unit' => 'hour']],
        ['from' => ['type' => 'recurring', 'billing_period' => 1, 'billing_unit' => 'month'],
            'to' => ['billing_period' => 720, 'billing_unit' => 'hour']],
    ];

    public function up(): void
    {
        foreach (self::MOVES as $move) {
            DB::table('plans')->where($move['from'])->update($move['to']);
        }
    }

    public function down(): void
    {
        foreach (self::MOVES as $move) {
            DB::table('plans')
                ->where(['type' => $move['from']['type'], ...$move['to']])
                ->update(['billing_period' => $move['from']['billing_period'],
                    'billing_unit' => $move['from']['billing_unit']]);
        }
    }
};
