<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's Credit Management log.
 *
 * Core keeps one `credits` row per client per currency — a balance and nothing else. The
 * reference records every adjustment instead: when, how much, why, and which admin did it,
 * and the balance is what those entries add up to. Without that a credit is money moving
 * with no account of itself, which is exactly what its own screen says it will not allow:
 * "every credit adjustment, either addition or removal, requires a log entry".
 *
 * The balance on `credits` is still the authority core spends from; these rows are the
 * history beside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_credit_entries')) {
            return;
        }

        Schema::create('ext_credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            // The reference lets staff date an entry, so a credit agreed last week can be
            // recorded as of last week.
            $table->date('entry_date');
            $table->string('description');
            // Signed: positive adds, negative removes. One column keeps the running total
            // a plain sum rather than two columns that have to agree.
            $table->decimal('amount', 17, 2);
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_credit_entries');
    }
};
