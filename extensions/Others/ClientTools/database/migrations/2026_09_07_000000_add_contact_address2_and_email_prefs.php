<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's Contacts screen carries two address lines and a set of Email
 * Preferences per contact (Leandro, 2026-09-07, screenshot of my.noxproxy.com):
 * General, Invoice, Support, Product and Domain emails, each a checkbox.
 *
 * The table had one address line and no preferences, so those parts of the screen had
 * nowhere to store anything. `email_preferences` is a json column holding the keys that
 * are ticked — a list rather than five booleans, so adding a sixth category later is a
 * label change rather than another migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ext_ct_contacts')) {
            return;
        }

        Schema::table('ext_ct_contacts', function (Blueprint $table): void {
            if (!Schema::hasColumn('ext_ct_contacts', 'address2')) {
                $table->string('address2')->nullable()->after('address');
            }

            if (!Schema::hasColumn('ext_ct_contacts', 'email_preferences')) {
                $table->json('email_preferences')->nullable()->after('permissions');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ext_ct_contacts')) {
            return;
        }

        Schema::table('ext_ct_contacts', function (Blueprint $table): void {
            foreach (['address2', 'email_preferences'] as $column) {
                if (Schema::hasColumn('ext_ct_contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
