<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The free-text Description an admin types on the reference's Add Transaction form.
 *
 * Core's `invoice_transactions` table (vendored, not touched) carries no description
 * column — the ledger has always synthesised one ("Invoice Payment (#214)"). WHMCS's own
 * field is a real, blank, editable box, and Leandro circled ours reading as a disabled
 * placeholder explaining why it wasn't. This table is that missing column, kept on the
 * side rather than added to core: one optional note per transaction, shown in the ledger
 * ahead of the synthesised text when one was actually typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_transaction_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')->unique()
                ->constrained('invoice_transactions')->cascadeOnDelete();

            $table->string('note');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_transaction_notes');
    }
};
