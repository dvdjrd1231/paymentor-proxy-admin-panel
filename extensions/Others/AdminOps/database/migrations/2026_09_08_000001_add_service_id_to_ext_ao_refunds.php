<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which service a refund was for, when it came from a cancellation rather than someone
 * pressing Refund on an invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ext_ao_refunds') || Schema::hasColumn('ext_ao_refunds', 'service_id')) {
            return;
        }

        Schema::table('ext_ao_refunds', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_id')->nullable()->after('invoice_id')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ext_ao_refunds') && Schema::hasColumn('ext_ao_refunds', 'service_id')) {
            Schema::table('ext_ao_refunds', function (Blueprint $table): void {
                $table->dropColumn('service_id');
            });
        }
    }
};
