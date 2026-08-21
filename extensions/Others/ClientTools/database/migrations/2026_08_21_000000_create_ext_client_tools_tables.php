<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the two client-area features Paymenter has no equivalent for.
 *
 * The other five pages this extension adds (Quotes, Mass Payment, Email History,
 * User Management, Available Addons) read data that already exists — invoices,
 * `email_logs`, `user_sessions` and `product_upgrades` — so they need no table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Contacts on an account, as the reference portal has them: extra people the
        // account holder can list, optionally promoted to a sub-account that may sign in.
        Schema::create('ext_ct_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->nullable();

            // A sub-account contact appears under User Management as someone with access.
            $table->boolean('is_sub_account')->default(false);

            // Which areas the sub-account may see — mirrors the reference's permission
            // checkboxes. JSON rather than columns so adding a permission needs no migration.
            $table->json('permissions')->nullable();

            $table->timestamps();

            // Every query is "this user's contacts", so the index matches the access path.
            $table->index(['user_id', 'is_sub_account']);
        });

        // Downloads the operator publishes (setup guides, proxy config files, tooling).
        Schema::create('ext_ct_downloads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('url');

            // A download may be public or restricted to signed-in customers, which is the
            // distinction the reference portal draws on its Downloads page.
            $table->boolean('requires_login')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('download_count')->default(0);
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ct_downloads');
        Schema::dropIfExists('ext_ct_contacts');
    }
};
