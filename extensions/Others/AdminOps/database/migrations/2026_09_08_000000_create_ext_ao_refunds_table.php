<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit returned to a client against an invoice (Leandro, 2026-09-08, defining what a
 * refund means here: "If client close or finish server service, the credit would be
 * return to client balance").
 *
 * Its own table rather than a row in `invoice_transactions`, and the distinction matters.
 * `Invoice::remaining` is computed as total minus succeeded transactions, so a negative
 * transaction would reopen the balance on an invoice that was genuinely paid — the client
 * would appear to owe money again, and the dunning that follows an unpaid invoice would
 * start. That is not what this is. The service was paid for and then ended early; the
 * client is being given credit for the unused part. The invoice stays settled.
 *
 * The balance itself lives where it always has, on `credits` — this table records why it
 * moved, which invoice it was against and who authorised it, so the question "why does
 * this client have credit?" has an answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_ao_refunds')) {
            return;
        }

        Schema::create('ext_ao_refunds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 20, 2);
            $table->string('currency_code', 8);
            $table->text('reason')->nullable();
            // Nullable so a refund survives the staff member who issued it being removed.
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ao_refunds');
    }
};
