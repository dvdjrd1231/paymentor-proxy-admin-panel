<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHMCS's OpenID Connect credential screen carries a Description and a Logo URL beside
 * the client's id and secret (Leandro, 2026-09-07: "The OpenId Connect page should be
 * updated as same as ... and have create / edit page").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('oauth_clients')) {
            return;
        }

        Schema::table('oauth_clients', function (Blueprint $table): void {
            if (!Schema::hasColumn('oauth_clients', 'description')) {
                $table->string('description')->nullable()->after('name');
            }

            if (!Schema::hasColumn('oauth_clients', 'logo_url')) {
                $table->string('logo_url')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('oauth_clients')) {
            return;
        }

        Schema::table('oauth_clients', function (Blueprint $table): void {
            foreach (['description', 'logo_url'] as $column) {
                if (Schema::hasColumn('oauth_clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
