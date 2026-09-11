<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The reference's **Billable Items**: an ad-hoc charge waiting for an invoice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_billable_items') || Schema::create('ext_billable_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Optional: a charge that belongs to a particular proxy reads very differently
            // on an invoice from one that belongs to the account.
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');

            // The reference's Hours/Qty and Amount, kept apart rather than pre-multiplied:
            // "3 hours at 40" is the thing anyone wants to see again later, and a single
            // total of 120 throws away which of the two was wrong.
            $table->decimal('amount', 17, 2);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('currency_code', 3);

            // The reference's Invoice Action, minus the two that only make sense in a system
            // with its due-date model. See Support\Items for the list and what each means.
            $table->string('invoice_action')->default('next_invoice');

            // The reference's Recurring Cycle — null is its "Never".
            $table->string('recur_every')->nullable();
            $table->date('next_due_at')->nullable();

            // Set when the charge lands on an invoice. Its presence is what makes an item
            // "invoiced", so it is written in the same transaction as the invoice item.
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable();

            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The sweeper's query: uninvoiced items, by action.
            $table->index(['invoiced_at', 'invoice_action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_billable_items');
    }
};
