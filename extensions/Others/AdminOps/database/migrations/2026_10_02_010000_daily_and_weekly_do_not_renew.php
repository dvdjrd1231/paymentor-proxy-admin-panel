<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Day and Week plans back to one-time, on their contracted hours.
 *
 * 2026_10_02_000000 made them recurring, reasoning that a one-time plan never gets an
 * expires_at and so is never terminated by core's cron. That is true of core, and it is
 * the whole premise of Others/TermLimits, which exists precisely because these products
 * must not renew: it keeps its own clock per service and sweeps every minute, and
 * Terms::length() recognises a fixed-term plan only when its type is 'one-time'. Making
 * them recurring therefore switched the thing that was ending them off, and would have
 * started raising renewal invoices as well.
 *
 * Leandro, 2026-10-02: "o serviço diario, não possuem renovação automatica: Finalizar após
 * 24:30 ... serviço semanal ... Finalizar após 168:30". So: no renewal, and a term of 24
 * or 168 hours plus half an hour of grace. The grace lives in Terms::GRACE_MINUTES, which
 * is why the periods here are a round 24 and 168 rather than the 25 and 169 that carried
 * an hour of grace inside the sold figure.
 *
 * Monthly is not touched: it is recurring, it bills on 720 hours, and an unpaid one
 * follows the panel's own suspend and terminate settings, which is Leandro's point 3.
 */
return new class extends Migration
{
    /** Hour-priced shapes, and the contracted length each should carry. */
    private const MOVES = [
        ['was' => 25, 'now' => 24],     // Day
        ['was' => 169, 'now' => 168],   // Week
    ];

    public function up(): void
    {
        foreach (self::MOVES as $move) {
            DB::table('plans')
                ->where('billing_unit', 'hour')
                ->whereIn('billing_period', [$move['was'], $move['now']])
                ->update(['type' => 'one-time', 'billing_period' => $move['now']]);
        }
    }

    public function down(): void
    {
        foreach (self::MOVES as $move) {
            DB::table('plans')
                ->where('billing_unit', 'hour')
                ->where('billing_period', $move['now'])
                ->update(['billing_period' => $move['was']]);
        }
    }
};
