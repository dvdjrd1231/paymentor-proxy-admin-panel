<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's "Recur Every … for N Times" and its Invoice Count.
 *
 * Without these the recurrence had no end: Items::repeat() queued the next period every
 * time an item was invoiced and nothing ever stopped it, and the admin form's "for N Times"
 * box accepted a number and discarded it (Leandro, 2026-09-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_billable_items', function (Blueprint $table) {
            // Null means "forever", which is what every existing row has been doing.
            $table->unsignedInteger('recur_times')->nullable()->after('recur_every');

            // How many times this item has been invoiced. The reference lets staff set it,
            // because it is what the limit above is counted against — putting it back to 0
            // restarts the cycle.
            $table->unsignedInteger('invoice_count')->default(0)->after('recur_times');
        });
    }

    public function down(): void
    {
        Schema::table('ext_billable_items', function (Blueprint $table) {
            $table->dropColumn(['recur_times', 'invoice_count']);
        });
    }
};
