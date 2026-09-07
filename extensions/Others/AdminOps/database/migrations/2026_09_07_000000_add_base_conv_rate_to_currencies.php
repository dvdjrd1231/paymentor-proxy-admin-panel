<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WHMCS's Currencies grid leads with Base Conv. Rate, and its currency editor is built
 * around it. Paymenter's `currencies` table has code, name, prefix, suffix and format and
 * nothing else — every price is stored per currency instead — so the column the reference
 * is organised around simply did not exist, and the grid showed a dash where the rate
 * belongs (Leandro, 2026-09-07: "these pages don't have 'Base Conv. Rate' Field. it is
 * basic foundation to update these pages").
 *
 * This adds it for real. What the number then *means* here is the same as in the
 * reference: how many units of this currency one unit of the base currency buys.
 * {@see \Paymenter\Extensions\Others\CurrencyRates\Support\RateSync} writes it on every
 * market sync, and the Currencies screen lets an admin set it by hand and rewrite prices
 * from that value — WHMCS's own two buttons, finally meaning two different things.
 *
 * `currencies` is a core table, so this is a guarded ALTER in the extension that needed
 * the column, exactly like {@see 2026_09_04_000001_add_whmcs_affiliate_fields}. Core never
 * reads or writes the column, so an install that later drops this extension keeps working;
 * `down()` removes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('currencies') || Schema::hasColumn('currencies', 'base_conv_rate')) {
            return;
        }

        Schema::table('currencies', function (Blueprint $table): void {
            // 8 decimals because a rate against a low-denomination currency needs them,
            // and the reference stores its own rates at the same precision.
            $table->decimal('base_conv_rate', 20, 8)->nullable()->after('format');
        });

        // The base currency converts to itself at 1 by definition. Everything else stays
        // null until a sync or an admin fills it, so the grid can honestly distinguish
        // "not yet known" from "known to be 1.00".
        $base = config('settings.default_currency');

        if (is_string($base) && $base !== '') {
            DB::table('currencies')->where('code', $base)->update(['base_conv_rate' => 1]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('currencies') && Schema::hasColumn('currencies', 'base_conv_rate')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->dropColumn('base_conv_rate');
            });
        }
    }
};
