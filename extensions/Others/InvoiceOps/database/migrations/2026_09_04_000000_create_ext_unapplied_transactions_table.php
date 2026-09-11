<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An "unapplied" transaction — money recorded with no invoice to apply it to, exactly
 * what the reference's Add Transaction allows and Paymenter's own schema cannot: core's
 * `invoice_transactions.invoice_id` is a required foreign key, not nullable, and staying
 * off vendored core (golden rule) means that stays true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_unapplied_transactions') || Schema::create('ext_unapplied_transactions', function (Blueprint $table) {
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
