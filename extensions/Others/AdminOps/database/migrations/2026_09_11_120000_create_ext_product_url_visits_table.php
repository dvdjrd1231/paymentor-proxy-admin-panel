<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hits per product storefront URL — the count behind Edit Product's Links tab. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_product_url_visits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            // The path as requested, so one product reached by two addresses counts
            // separately — which is the point of the reference's list. 191 keeps the
            // unique index inside MySQL's key length.
            $table->string('path', 191);
            $table->unsignedBigInteger('visits')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_product_url_visits');
    }
};
