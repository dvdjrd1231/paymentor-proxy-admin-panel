<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Day and Week plans, typed as what they are.
 *
 * They were written as 'one-time' when the hour-based cycles were added. Service::
 * calculateNextDueDate() returns null for a one-time plan, so expires_at was never set on
 * anything sold on them — and the daily cron's invoice, suspend and terminate steps all
 * filter on expires_at. A service on a Day plan was therefore invisible to every one of
 * them and ran for ever (Leandro, 2026-10-02: "I identified an issue with the active
 * services; they are not being terminated automatically").
 *
 * Only the hour-priced plans are touched, and only the two periods the catalogue uses:
 * 25 hours (Day) and 169 (Week). A genuine one-time plan -- the VPS and Test Payment ones,
 * which carry no billing period at all -- is left alone, because never expiring is what a
 * one-time plan is for.
 *
 * This fixes the catalogue, so every service ordered from here on gets an expiry. It does
 * NOT backfill the services already sold on these plans: giving one an expires_at that is
 * already in the past hands it straight to the terminate step, and on this platform that
 * deletes the client's proxy allocation. Which of those to settle, and when, is the
 * operator's call -- see docs/CORE-TOUCHPOINTS.md.
 */
return new class extends Migration
{
    /** Hours per cycle, as EditProduct::CYCLES defines them. */
    private const HOURLY_PERIODS = [25, 169];

    public function up(): void
    {
        DB::table('plans')
            ->where('type', 'one-time')
            ->where('billing_unit', 'hour')
            ->whereIn('billing_period', self::HOURLY_PERIODS)
            ->update(['type' => 'recurring', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('plans')
            ->where('type', 'recurring')
            ->where('billing_unit', 'hour')
            ->whereIn('billing_period', self::HOURLY_PERIODS)
            ->update(['type' => 'one-time', 'updated_at' => now()]);
    }
};
