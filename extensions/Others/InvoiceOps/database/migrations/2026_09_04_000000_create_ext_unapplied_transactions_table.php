<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An "unapplied" transaction — money recorded with no invoice to apply it to, exactly
 * what the reference's Add Transaction allows and Paymenter's own schema cannot: core's
 * `invoice_transactions.invoice_id` is a required foreign key, not nullable, and staying
 * off vendored core (golden rule) means that stays true.
 *
 * A nullable column was tried first and reverted before it ever reached a deploy. Core's
 * own `InvoiceTransactionCreatedListener` (app/Listeners) runs on every created/updated
 * InvoiceTransaction and does `$event->invoiceTransaction->invoice->remaining` with no
 * null guard — a row with `invoice_id = null` would throw there synchronously, in-request,
 * the instant one was created. That listener is vendored; not ours to edit. A side table
 * this extension owns entirely sidesteps it — core's own transaction machinery, and that
 * listener, never see one of these rows at all.
 *
 * Deliberately thin: no `applied_at`/`invoice_id` column for "apply this to an invoice
 * later", the reference's own next step for one of these. That is real, wanted work this
 * migration does not pre-empt — the columns here are only what recording one needs today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_unapplied_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gateway_id')->nullable()->constrained('extensions')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('amount', 17, 2);
            $table->decimal('fee', 17, 2)->default(0);
            $table->string('currency_code', 3);
            $table->string('transaction_id')->nullable();
            $table->string('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_unapplied_transactions');
    }
};
