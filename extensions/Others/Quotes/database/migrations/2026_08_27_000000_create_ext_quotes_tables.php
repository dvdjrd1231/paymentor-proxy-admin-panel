<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotes: a priced proposal a customer can accept, which then becomes an invoice.
 *
 * Paymenter has no document that is not already a bill. An invoice is `pending`, `paid` or
 * `cancelled`, and none of those can stand in for "here is what it would cost" without
 * misrepresenting a proposal as a debt — the customer sees it in their invoice list, the
 * overdue ladder starts counting, and a reminder goes out for money nobody agreed to pay.
 *
 * So a quote is its own record, with its own life, and only becomes an invoice at the moment
 * somebody accepts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_quotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('subject');
            $table->string('currency_code', 3);

            // draft -> sent -> accepted | declined | expired. Draft is the reference's own
            // starting state and the reason quotes are safe to write in front of a customer:
            // nothing is visible to them until it is sent.
            $table->string('status')->default('draft');

            // The reference's Valid / Expired split. Nullable means a quote with no deadline,
            // which is a legitimate thing to offer and must not be swept as expired.
            $table->date('valid_until')->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();

            // The invoice this quote turned into. Its presence is what stops one quote being
            // accepted into two invoices.
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The expiry sweep's only query.
            $table->index(['status', 'valid_until']);
        });

        Schema::create('ext_quote_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quote_id')->constrained('ext_quotes')->cascadeOnDelete();

            $table->string('description');
            $table->decimal('price', 17, 2);
            $table->decimal('quantity', 10, 2)->default(1);

            // Dragged into order in the admin form, as the catalogue is.
            $table->unsignedSmallInteger('sort')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_quote_items');
        Schema::dropIfExists('ext_quotes');
    }
};
