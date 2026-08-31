<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7: an addon is a service in its own right (that is what makes core bill and renew
 * it), and this table is the one fact core cannot hold — which service it belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_service_addons') || Schema::create('ext_service_addons', function (Blueprint $table) {
            $table->id();
            // The addon's own service row, and the service it extends. Plain indexed
            // columns, no FK — same errno-150 ground as ext_network_issues.
            $table->unsignedBigInteger('service_id')->unique();
            $table->unsignedBigInteger('parent_service_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_service_addons');
    }
};
