<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;

/** A customer asking for their money back, and the answer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_refund_requests') || Schema::create('ext_refund_requests', function (Blueprint $table) {
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
