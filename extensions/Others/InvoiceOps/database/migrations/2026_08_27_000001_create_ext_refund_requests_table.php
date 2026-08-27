<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;

/**
 * A customer asking for their money back, and the answer.
 *
 * The request is the piece that makes refunds workable without a gateway API. Paymenter
 * cannot move money back through Stripe — no gateway here defines a `refund()` — but the
 * decision, the reason and the record are the parts a business actually needs, and none of
 * them require the API. You approve here, refund in the gateway's own dashboard, and the
 * approval writes the {@see InvoiceRefund}
 * that the ledger and the Amount Out column read.
 *
 * Same shape as a cancellation request, deliberately: the customer asks, an administrator
 * answers, and the answer is on the record with its reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_refund_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Null means "all of it" — the reference's blank-for-full-refund, kept as an
            // absence rather than resolved at request time, because the invoice total can
            // change between the asking and the answering.
            $table->decimal('amount', 17, 2)->nullable();

            $table->text('reason');

            $table->string('status')->default('pending');

            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();

            // Set on approval: the refund this request produced. Its presence is what stops
            // one request being approved into two refunds.
            $table->foreignId('refund_id')->nullable()
                ->constrained('ext_invoice_refunds')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_refund_requests');
    }
};
