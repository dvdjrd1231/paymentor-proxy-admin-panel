<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's ticket sidebar carries a Tag Cloud, a Watch Ticket button and a Ticket
 * Watchers list. All three were left off this screen because nothing stored them, which
 * left the rail visibly short of Leandro's screenshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ext_ticket_meta')) {
            return;
        }

        Schema::table('ext_ticket_meta', function (Blueprint $table) {
            Schema::hasColumn('ext_ticket_meta', 'tags') || $table->string('tags')->nullable();
            Schema::hasColumn('ext_ticket_meta', 'watchers') || $table->text('watchers')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ext_ticket_meta')) {
            return;
        }

        Schema::table('ext_ticket_meta', function (Blueprint $table) {
            $table->dropColumn(['tags', 'watchers']);
        });
    }
};
