<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's Description on a transaction.
 *
 * Purely additive: core never selects this column, so nothing upstream changes behaviour.
 * The alternative — making `invoice_id` nullable so a transaction can stand alone as the
 * reference allows — is NOT safe here: InvoiceTransaction's own amount and fee accessors
 * read `$this->invoice->currency`, and SendMailListener reads `$transaction->invoice->user`,
 * so an invoice-less row would fatal wherever it is rendered. Money that belongs to the
 * client rather than to an invoice goes to their credit balance instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoice_transactions', 'description')) {
            return;
        }

        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->string('description')->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
