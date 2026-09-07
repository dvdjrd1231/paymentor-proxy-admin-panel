<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHMCS's API Roles (Leandro, 2026-09-07: "the Role Management modal contain many
 * options") — a named, reusable set of allowed API actions that credentials are assigned
 * to, instead of ticking the same hundred boxes on every credential.
 *
 * Paymenter stores permissions per API key and has no such reusable set, which is why the
 * page used to say the tab had no equivalent. It does now: a role is a saved list of the
 * ability keys core already understands (`config('permissions.api')`).
 *
 * The credential's own `permissions` column stays the enforced thing — core's API
 * middleware reads that and nothing else. Assigning roles writes the union of their
 * abilities into it, and the pivot records which roles produced it so the screen can show
 * the assignment and re-apply it when a role changes. Nothing here bends core's
 * authorisation; it only fills the column that authorisation already reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_api_roles') || Schema::create('ext_api_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            // The ability keys this role grants, e.g. ["admin.users.viewAny", ...].
            $table->json('permissions');
            $table->timestamps();
        });

        Schema::hasTable('ext_api_key_roles') || Schema::create('ext_api_key_roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('api_key_id')->index();
            $table->unsignedBigInteger('api_role_id')->index();
            $table->unique(['api_key_id', 'api_role_id']);
        });
    }

    public function down(): void
    {
        // Credentials keep the permissions they were granted — dropping this module must
        // not silently revoke access that external systems are already authenticating with.
        Schema::dropIfExists('ext_api_key_roles');
        Schema::dropIfExists('ext_api_roles');
    }
};
