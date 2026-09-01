<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #38: a Brazilian registration is one of two things, and which one decides the whole
 * set of documents required.
 *
 * - Pessoa Física — a private citizen: RG and CPF.
 * - Pessoa Jurídica — a constituted company: CNPJ, plus Inscrição Estadual (which may be
 *   declared exempt instead of given) and Inscrição Municipal.
 *
 * The original seed deliberately left this out and showed every field at once, on the
 * reading that the customer could pick what applied. Leandro's feedback reverses that: the
 * documents are not interchangeable, and asking a company for a CPF is asking the wrong
 * question. The selector is what the rest of the block keys off, in the admin's Add New
 * Client and on the client's own registration form.
 *
 * Values are stored as written here, so they read correctly on a tax document.
 */
return new class extends Migration
{
    private const MODEL = 'App\\Models\\User';

    public const INDIVIDUAL = 'Pessoa Física (Individual)';

    public const COMPANY = 'Pessoa Jurídica (Company)';

    public function up(): void
    {
        DB::table('custom_properties')->updateOrInsert(
            ['key' => 'person_type'],
            [
                'name' => 'Person Type / Tipo de Pessoa',
                'description' => null,
                'type' => 'select',
                'model' => self::MODEL,
                'validation' => null,
                'allowed_values' => json_encode([self::INDIVIDUAL, self::COMPANY]),
                'non_editable' => false,
                // Never required at the column level: the whole Brazilian block only
                // renders when the country is Brazil, and a field that cannot be seen
                // cannot be demanded. Brazil's own requirement is enforced by the forms.
                'required' => false,
                'show_on_invoice' => true,
            ]
        );
    }

    public function down(): void
    {
        DB::table('custom_properties')->where('model', self::MODEL)->where('key', 'person_type')->delete();
    }
};
