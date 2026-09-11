<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Issue #38: make the client's own registration form demand what Brazil demands. */
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
