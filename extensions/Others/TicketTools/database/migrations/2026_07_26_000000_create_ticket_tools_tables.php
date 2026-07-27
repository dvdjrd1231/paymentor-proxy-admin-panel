<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket tooling: canned (quick) replies + staff-only internal notes.
 *
 * These are additive tables — core ticket tables are untouched, so upgrades are safe.
 * Internal notes live in their own table the client theme never renders, so they are
 * never exposed to customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canned_responses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('department')->nullable(); // optional scoping to a department
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('ticket_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id')->nullable(); // staff author
            $table->text('body');
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_notes');
        Schema::dropIfExists('canned_responses');
    }
};
