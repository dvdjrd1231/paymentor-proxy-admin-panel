<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The free-text Description an admin types on the reference's Add Transaction form. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_transaction_notes') || Schema::create('ext_transaction_notes', function (Blueprint $table) {
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
