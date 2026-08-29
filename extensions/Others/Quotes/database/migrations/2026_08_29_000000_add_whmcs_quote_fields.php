<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's Create New Quote fields: a per-line discount and taxed flag, and the
 * three note boxes — proposal text shown at the top of the quote, customer notes as its
 * footer, with the existing `notes` column keeping its role as the admin-only box.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_quotes', function (Blueprint $table) {
            $table->text('proposal_text')->nullable()->after('notes');
            $table->text('customer_notes')->nullable()->after('proposal_text');
        });

        Schema::table('ext_quote_items', function (Blueprint $table) {
            $table->decimal('discount', 5, 2)->default(0)->after('quantity');
            $table->boolean('taxed')->default(false)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('ext_quotes', function (Blueprint $table) {
            $table->dropColumn(['proposal_text', 'customer_notes']);
        });

        Schema::table('ext_quote_items', function (Blueprint $table) {
            $table->dropColumn(['discount', 'taxed']);
        });
    }
};
