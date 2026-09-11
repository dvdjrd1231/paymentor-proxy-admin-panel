<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Refunds, as the reference's Refund tab records them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_invoice_refunds') || Schema::create('ext_invoice_refunds', function (Blueprint $table) {
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
