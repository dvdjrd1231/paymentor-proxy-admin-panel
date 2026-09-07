<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHMCS's Client Groups (Leandro, 2026-09-07: "Is there client group function in the
 * Paymenter project? ... this pages should be clear and update to same as
 * configclientgroups.php").
 *
 * The answer to his question was no — Paymenter has no such concept, and the Client
 * Profile's "Client Group: None" row was a parity placeholder reading a value that could
 * never be anything else. This gives it something to read.
 *
 * The reference's five columns, kept as its own table rather than added to `users`: a
 * group is a record clients point at, not a property each client owns.
 *
 * A client's membership is stored as the user property `client_group_id`, which is how
 * every other per-client value this deployment adds is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_client_groups') || Schema::create('ext_client_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            // The reference's colour swatch, used to tint the client's name in lists.
            $table->string('colour', 9)->default('#ffffff');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('suspend_exempt')->default(false);
            $table->boolean('separate_invoices')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_client_groups');
    }
};
