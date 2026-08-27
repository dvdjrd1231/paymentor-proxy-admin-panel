<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's **Auto Terminate/Fixed Term** field, per product.
 *
 * On WHMCS this lives on the product's Pricing tab: *"Enter the number of days after
 * activation to automatically terminate (eg. free trials, time limited products, etc...)"*,
 * with `0` meaning off, and a **Termination Email** beside it — *"Choose the email template
 * to send when the fixed term comes to an end"*.
 *
 * It is a table rather than a column on `products` for the usual reason: this extension does
 * not edit core, and a column core does not know about is a column core's own migrations
 * could collide with. A product with no row here falls back to the term derived from its
 * plan, which is the case for all twenty of this store's daily and weekly products — the
 * reference needs the field because a WHMCS "One Time" product carries no period at all,
 * and Paymenter's does.
 *
 * The point of having it anyway is the case derivation cannot reach: a **fixed term on a
 * recurring product**, which is how a free trial is built. "Monthly plan, terminates after
 * 3 days" is not something a billing cycle can express.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_term_limit_products', function (Blueprint $table) {
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
