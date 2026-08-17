<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rate_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('currency_code', 8);

            // The value this module last wrote. A stored price that no longer matches it has
            // been edited by hand, and the sync leaves it alone from then on.
            $table->decimal('auto_price', 16, 2);
            $table->decimal('rate_used', 18, 8);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'currency_code']);
            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rate_prices');
    }
};
