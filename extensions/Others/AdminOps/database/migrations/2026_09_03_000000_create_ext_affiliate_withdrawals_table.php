<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #6's "How are withdrawals processed?" — they are paid outside the panel (bank,
 * PIX, credit) and recorded here, which is exactly the reference's model: WHMCS's
 * withdrawal history is a ledger of payouts an admin says happened. The affiliate
 * extension itself keeps no such ledger, so this table is AdminOps's own rather than a
 * change to that extension.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_affiliate_withdrawals') || Schema::create('ext_affiliate_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_id')->index();
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_affiliate_withdrawals');
    }
};
