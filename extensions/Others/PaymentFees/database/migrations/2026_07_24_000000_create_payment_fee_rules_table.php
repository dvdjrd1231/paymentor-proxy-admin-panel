<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Which gateway this fee applies to. Matches App\Models\Gateway.extension
            // (the extension name, e.g. "Stripe", "CoinPayments"). Null = any gateway.
            $table->string('gateway')->nullable();

            // fixed | percent | both
            $table->string('fee_type')->default('fixed');
            $table->decimal('fixed_amount', 12, 2)->default(0);
            $table->decimal('percent_amount', 7, 4)->default(0); // e.g. 2.9000 = 2.9%

            // Scoping — any left null means "not scoped by this".
            $table->char('country', 2)->nullable();          // customer country (ISO-2) or name
            $table->string('currency_code', 8)->nullable();  // invoice currency
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('customer_type')->nullable();     // individual | business
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();

            $table->integer('priority')->default(100);       // lower = evaluated first
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['gateway', 'active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_fee_rules');
    }
};
