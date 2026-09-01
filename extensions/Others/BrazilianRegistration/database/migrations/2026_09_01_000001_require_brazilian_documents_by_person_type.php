<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #38: make the client's own registration form demand what Brazil demands.
 *
 * Core builds each property's rules as `nullable|<validation>`, so the `validation` column
 * is the whole hook — no core edit and no per-form code. The four rules named here are
 * registered by the extension and read the sibling answers (country, person type, the
 * Isento tick) off the form being validated, which is the part `required` and `required_if`
 * cannot do: the requirement is conditional on two other fields, and one of the values is
 * an accented label that must stay free to be reworded.
 *
 * Deliberately *not* setting the `required` column: that would demand a CPF of every
 * customer in the world, Brazilian or not.
 *
 * down() restores the plain rules, leaving the fields optional as they were.
 */
return new class extends Migration
{
    private const MODEL = 'App\\Models\\User';

    /** key => [rules after this migration, rules before it] */
    private const RULES = [
        'person_type' => ['brazil_person_type', null],
        'cpf' => ['cpf_required|cpf', 'cpf'],
        'cnpj' => ['cnpj_required|cnpj', 'cnpj'],
        'state_registration' => ['ie_or_exempt|max:30', 'max:30'],
    ];

    public function up(): void
    {
        foreach (self::RULES as $key => [$after]) {
            DB::table('custom_properties')
                ->where('model', self::MODEL)
                ->where('key', $key)
                ->update(['validation' => $after]);
        }
    }

    public function down(): void
    {
        foreach (self::RULES as $key => [, $before]) {
            DB::table('custom_properties')
                ->where('model', self::MODEL)
                ->where('key', $key)
                ->update(['validation' => $before]);
        }
    }
};
