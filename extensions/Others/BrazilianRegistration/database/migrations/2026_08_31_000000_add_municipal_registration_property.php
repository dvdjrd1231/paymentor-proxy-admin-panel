<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #32: companies registering from Brazil also carry a Municipal Tax Registration
 * (Inscrição Municipal), alongside the CNPJ and the State Registration already seeded.
 * Same column set as the original seed — `custom_properties` has no sort or timestamps.
 */
return new class extends Migration
{
    private const MODEL = 'App\\Models\\User';

    public function up(): void
    {
        DB::table('custom_properties')->updateOrInsert(
            ['key' => 'municipal_registration'],
            [
                'name' => 'Municipal Tax Registration / Inscrição Municipal',
                'description' => null,
                'type' => 'text',
                'model' => self::MODEL,
                'validation' => null,
                'allowed_values' => null,
                'non_editable' => false,
                'required' => false,
                'show_on_invoice' => true,
            ]
        );
    }

    public function down(): void
    {
        DB::table('custom_properties')->where('key', 'municipal_registration')->delete();
    }
};
