<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Gateway this rule targets (App\Models\Gateway.extension). Null = any.
            $table->string('gateway')->nullable();

            // allow | deny — decision when this rule matches.
            $table->string('mode')->default('deny');

            // Scoping — null means "not scoped by this".
            $table->char('country', 2)->nullable();          // customer country (ISO-2) or name
            $table->string('currency_code', 8)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable(); // product group
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
        Schema::dropIfExists('gateway_rules');
    }
};
