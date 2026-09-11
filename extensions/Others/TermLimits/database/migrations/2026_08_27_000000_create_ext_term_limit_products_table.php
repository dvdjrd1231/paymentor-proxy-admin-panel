<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The reference's **Auto Terminate/Fixed Term** field, per product. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::hasTable('ext_term_limit_products') || Schema::create('ext_term_limit_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();

            // Days, as the reference counts them, rather than the hours a term is stored in:
            // this is the number an administrator types, and converting on the way in keeps
            // the two units from being confused in the one place a human touches them.
            // 0 is the reference's "off" — kept rather than deleting the row, so turning it
            // off and on again does not lose the email template beside it.
            $table->unsignedSmallInteger('days')->default(0);

            // The reference's Termination Email. Null means the default, which is core's
            // `server_terminated` — never *no* email: a proxy that stops without a word is
            // the support ticket this field exists to prevent.
            $table->string('termination_email')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_term_limit_products');
    }
};
