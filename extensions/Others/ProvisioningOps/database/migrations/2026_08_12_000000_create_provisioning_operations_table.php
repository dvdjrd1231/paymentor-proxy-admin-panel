<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_operations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('service_id');
            // Server extension that ran the operation, e.g. "ProxyPanel".
            $table->string('extension');
            // create | suspend | unsuspend | terminate | upgrade | callback | …
            $table->string('action');

            // failed | succeeded — a row only exists once an operation has been attempted.
            $table->string('status')->default('failed');

            $table->unsignedInteger('attempts')->default(1);
            $table->text('error')->nullable();
            // Arbitrary diagnostic payload (HTTP status, panel response, retry origin, …).
            $table->json('context')->nullable();

            // Set when a failure was later resolved (by a retry or by a panel callback).
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();

            $table->timestamps();

            // The admin list is "newest failures first", and retries look a row up by
            // (service, extension, action) so repeated failures collapse into one row.
            $table->index(['status', 'created_at']);
            $table->unique(['service_id', 'extension', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_operations');
    }
};
