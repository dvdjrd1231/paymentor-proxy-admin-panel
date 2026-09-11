<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's ticket Options tab carries CC Recipients and Prevent Client Closure —
 * per-ticket facts core's tickets table has no columns for (Leandro's tab-by-tab
 * screenshots, 2026-09-06). Same AdminOps-owned side-table pattern as the affiliate
 * ledgers: core stays untouched, the extension owns the columns it needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_ticket_meta') || Schema::create('ext_ticket_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->unique();
            $table->string('cc')->nullable();
            $table->boolean('prevent_closure')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ticket_meta');
    }
};
