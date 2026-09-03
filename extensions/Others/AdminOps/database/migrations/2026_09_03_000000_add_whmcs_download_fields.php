<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's Add Download form carries Type, Clients Only, Product Download and
 * Hidden, and Add Category carries Check to Hide — columns the first cut of the tables
 * did not have. Guarded column by column, same as the tables themselves, because
 * `installed()` re-runs every migration on each enable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_downloads') && !Schema::hasColumn('ext_downloads', 'type')) {
            Schema::table('ext_downloads', function (Blueprint $table): void {
                $table->string('type')->nullable();
                $table->boolean('clients_only')->default(false);
                $table->boolean('product_download')->default(false);
                $table->boolean('hidden')->default(false);
            });
        }

        if (Schema::hasTable('ext_download_categories') && !Schema::hasColumn('ext_download_categories', 'hidden')) {
            Schema::table('ext_download_categories', function (Blueprint $table): void {
                $table->boolean('hidden')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ext_downloads') && Schema::hasColumn('ext_downloads', 'type')) {
            Schema::table('ext_downloads', function (Blueprint $table): void {
                $table->dropColumn(['type', 'clients_only', 'product_download', 'hidden']);
            });
        }

        if (Schema::hasTable('ext_download_categories') && Schema::hasColumn('ext_download_categories', 'hidden')) {
            Schema::table('ext_download_categories', function (Blueprint $table): void {
                $table->dropColumn('hidden');
            });
        }
    }
};
