<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds, as the reference's Refund tab records them.
 *
 * A table of its own rather than a negative row in `invoice_transactions`, which was the
 * tempting option because it would make income net itself out for free. It would also make
 * `Invoice::$remaining` positive again — core computes what is owed by summing transactions
 * — so a refunded invoice would read as unpaid, and the daily cron would start chasing it
 * and eventually suspend the service it belongs to. A refund is not a debt.
 *
 * The invoice instead moves to `refunded`, which is the reference's own status and which no
 * overdue query matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_invoice_refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Which payment is being given back. Nullable because a refund can legitimately
            // be recorded against an invoice whose transaction was never captured here — an
            // offline payment, or one settled before this store existed.
            $table->foreignId('transaction_id')->nullable()
                ->constrained('invoice_transactions')->nullOnDelete();

            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('amount', 17, 2);
            $table->string('currency_code', 3);

            // The reference's "Refund Type": through the gateway, or recorded as done
            // elsewhere. Stored rather than inferred, because "did the money actually move"
            // is the question anyone asks about a refund six months later.
            $table->string('method')->default('offline');

            $table->text('reason')->nullable();

            // The reference's "Reverse Payment — undo automated actions triggered by this
            // transaction, where possible".
            $table->boolean('reversed_service')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_invoice_refunds');
    }
};
