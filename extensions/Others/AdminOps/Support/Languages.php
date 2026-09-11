<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

/**
 * The languages a client can be recorded as reading, and that an email template can be
 * translated into — the reference's own list (Leandro's Manage Languages screenshot,
 * 2026-09-09), not the `lang/` directories.
 */
class Languages
{
    /**
     * Code => name, in the reference's order (its own, alphabetical by name).
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'ar' => 'Arabic',
            'az' => 'Azerbaijani',
            'ca' => 'Catalan',
            'zh' => 'Chinese',
            'hr' => 'Croatian',
            'cs' => 'Czech',
            'da' => 'Danish',
            'nl' => 'Dutch',
            'en' => 'English',
            'et' => 'Estonian',
            'fa' => 'Farsi',
            'fr' => 'French',
            'de' => 'German',
            'he' => 'Hebrew',
            'hu' => 'Hungarian',
            'it' => 'Italian',
            'mk' => 'Macedonian',
            'nb' => 'Norwegian',
            'pt-br' => 'Portuguese-br',
            'pt' => 'Portuguese-pt',
            'ro' => 'Romanian',
            'ru' => 'Russian',
            'es' => 'Spanish',
            'sv' => 'Swedish',
            'tr' => 'Turkish',
            'uk' => 'Ukrainian',
        ];
    }

    /** One language's name, or its code upper-cased when it is not one we list. */
    public static function name(string $code): string
    {
        return static::all()[$code] ?? strtoupper($code);
    }
}
