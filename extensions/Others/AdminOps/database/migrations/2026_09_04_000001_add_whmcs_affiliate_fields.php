<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference's Affiliate detail screen carries a Commission Type (Use Default /
 * Percentage / Fixed Amount) and a Pay One Time Only switch — neither of which the
 * Affiliates extension's own table has a column for. Same treatment as issue #46's
 * Currencies-fields migration: guarded column-by-column, added here rather than in that
 * extension's own migrations, because `installed()` re-runs every migration on enable and
 * this file belongs to the feature that needed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_affiliates') && !Schema::hasColumn('ext_affiliates', 'commission_type')) {
            Schema::table('ext_affiliates', function (Blueprint $table): void {
                $table->string('commission_type')->nullable()->after('reward');
                $table->boolean('one_time_only')->default(false)->after('commission_type');
            });
        }

        Schema::hasTable('ext_affiliate_manual_commissions') || Schema::create('ext_affiliate_manual_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_id')->index();
            // The reference's "Related Referral" — optional, since a manual entry need not
            // trace to one of the affiliate's own referred orders.
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ext_affiliates') && Schema::hasColumn('ext_affiliates', 'commission_type')) {
            Schema::table('ext_affiliates', function (Blueprint $table): void {
                $table->dropColumn(['commission_type', 'one_time_only']);
            });
        }

        Schema::dropIfExists('ext_affiliate_manual_commissions');
    }
};
