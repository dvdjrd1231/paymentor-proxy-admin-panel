<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the Brazilian tax fields into Paymenter's Custom Properties (model =
 * User). These render automatically on the registration + account forms via
 * <x-form.properties> and validate server-side using the `validation` column.
 *
 * Reversible: down() removes the field definitions; the FK cascade on
 * properties.custom_property_id removes any stored values with them.
 */
return new class extends Migration
{
    private const MODEL = 'App\Models\User';

    private function fields(): array
    {
        return [
            [
                'key' => 'person_type',
                'name' => 'Person Type / Tipo de Pessoa',
                'type' => 'select',
                'validation' => null,
                'allowed_values' => ['individual' => 'Pessoa Física (Individual)', 'business' => 'Pessoa Jurídica (Business)'],
                'required' => true,
                'show_on_invoice' => false,
            ],
            [
                'key' => 'cpf',
                'name' => 'CPF',
                'type' => 'string',
                'validation' => 'cpf',
                'allowed_values' => null,
                'required' => false,
                'show_on_invoice' => false,
            ],
            [
                'key' => 'rg',
                'name' => 'RG',
                'type' => 'string',
                'validation' => 'max:20',
                'allowed_values' => null,
                'required' => false,
                'show_on_invoice' => false,
            ],
            [
                'key' => 'company_name',
                'name' => 'Company Name / Razão Social',
                'type' => 'string',
                'validation' => 'max:191',
                'allowed_values' => null,
                'required' => false,
                'show_on_invoice' => true,
            ],
            [
                'key' => 'trade_name',
                'name' => 'Trade Name / Nome Fantasia',
                'type' => 'string',
                'validation' => 'max:191',
                'allowed_values' => null,
                'required' => false,
                'show_on_invoice' => false,
            ],
            [
                'key' => 'cnpj',
                'name' => 'CNPJ',
                'type' => 'string',
                'validation' => 'cnpj',
                'allowed_values' => null,
                'required' => false,
                'show_on_invoice' => true,
            ],
            [
                'key' => 'state_registration',
                'name' => 'State Registration / Inscrição Estadual',
                'type' => 'string',
                'validation' => 'max:30',
                'allowed_values' => null,
                'required' => false,
                'show_on_invoice' => false,
            ],
            [
                'key' => 'state_registration_exempt',
                'name' => 'State Registration Exempt / Isento de IE',
                'type' => 'checkbox',
                'validation' => null,
                'allowed_values' => null,
                'required' => false,
                'show_on_invoice' => false,
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->fields() as $field) {
            DB::table('custom_properties')->updateOrInsert(
                ['key' => $field['key']],
                [
                    'name' => $field['name'],
                    'description' => null,
                    'type' => $field['type'],
                    'model' => self::MODEL,
                    'validation' => $field['validation'],
                    'allowed_values' => $field['allowed_values'] !== null ? json_encode($field['allowed_values']) : null,
                    'non_editable' => false,
                    'required' => $field['required'],
                    'show_on_invoice' => $field['show_on_invoice'],
                ]
            );
        }
    }

    public function down(): void
    {
        $keys = array_column($this->fields(), 'key');
        DB::table('custom_properties')->where('model', self::MODEL)->whereIn('key', $keys)->delete();
    }
};
