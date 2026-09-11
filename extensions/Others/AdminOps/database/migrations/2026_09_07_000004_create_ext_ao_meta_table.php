<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra attributes the reference's Products/Services screens carry that core's own
 * `categories` and `products` tables have no column for (Leandro, 2026-09-07: the catalogue
 * and its sub-pages must match the WHMCS screenshots).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_ao_meta')) {
            return;
        }

        Schema::create('ext_ao_meta', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            // One value per key per record, and the lookup this table is always read by.
            $table->unique(['model_type', 'model_id', 'key'], 'ext_ao_meta_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ao_meta');
    }
};
