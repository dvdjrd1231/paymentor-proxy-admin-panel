<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Drop the downloads table. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ext_ct_downloads');
    }

    public function down(): void
    {
        // Recreated exactly as the original migration had it, so a rollback restores the
        // schema this replaced rather than a half-shaped table.
        Schema::hasTable('ext_ct_downloads') || Schema::create('ext_ct_downloads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('url');
            $table->boolean('requires_login')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('download_count')->default(0);
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }
};
