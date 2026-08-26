<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clock on a fixed-term service, and every hand that has moved it.
 *
 * Kept beside `services` rather than in it for two reasons. The column that looks like it
 * would do — `services.expires_at` — is cast to `date` in core, so it cannot hold an hour;
 * and a daily product measured to the day is a product that runs between one and two days.
 * The other is that the extension owns this behaviour: disable it and the table is simply
 * not read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_term_limits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')->unique()->constrained()->cascadeOnDelete();

            // What was bought, in hours, before any extension. Stored rather than derived
            // from the plan: a plan that is re-priced or re-timed later must not silently
            // change the length of a term somebody has already paid for.
            $table->unsignedInteger('hours');

            $table->dateTime('started_at');
            $table->dateTime('ends_at');

            // Set when the sweeper closes the term. Its presence is what stops the term
            // being acted on twice, so it is written in the same transaction as the action.
            $table->dateTime('ended_at')->nullable();
            $table->string('outcome')->nullable();

            $table->timestamps();

            // The sweeper's only query: open terms that are due.
            $table->index(['ended_at', 'ends_at']);
        });

        Schema::create('ext_term_limit_extensions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('term_id')->constrained('ext_term_limits')->cascadeOnDelete();

            // Nullable so an extension granted by an administrator who later leaves is not
            // deleted with them: the record of *why* a customer got extra time outlives the
            // account of whoever granted it.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->integer('hours');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_term_limit_extensions');
        Schema::dropIfExists('ext_term_limits');
    }
};
