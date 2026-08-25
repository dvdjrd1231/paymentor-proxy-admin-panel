<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per provisioned proxy, replacing a comma-joined list in `properties.value`.
 *
 * That column is `TEXT`, so it holds about 1,213 endpoints. Every product in the live
 * catalogue sells more than that — the smallest tier is 1,500 ports (~81 KB) and the largest
 * is 31,500 (~1.7 MB) — so **every** order failed at the point of storing the panel's reply:
 *
 *   SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'value'
 *
 * The panel had already allocated the proxies by then, so each failure also leaked capacity.
 *
 * Widening `properties.value` was the smaller change and was rejected: `properties` is a core
 * table shared by every model, and it would still mean loading and exploding a 1.7 MB string
 * on each client-area render. Rows are the shape this data actually has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxypanel_endpoints', function (Blueprint $table) {
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
