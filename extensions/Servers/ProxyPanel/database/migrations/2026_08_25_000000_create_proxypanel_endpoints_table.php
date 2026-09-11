<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per provisioned proxy, replacing a comma-joined list in `properties.value`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('proxypanel_endpoints') || Schema::create('proxypanel_endpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            // 45 covers the longest IPv6 form, including an IPv4-mapped tail.
            $table->string('host', 45);
            $table->unsignedInteger('port');

            // Every read is "all endpoints for this service", in insertion order.
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxypanel_endpoints');
    }
};
