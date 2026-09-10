<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

/**
 * The languages a client can be recorded as reading, and that an email template can be
 * translated into — the reference's own list (Leandro's Manage Languages screenshot,
 * 2026-09-09), not the `lang/` directories.
 *
 * The two are different questions. `lang/` says which languages the *interface* is
 * translated into, and this install ships two. What a client reads is a fact about the
 * client, and the reference offers the full list regardless — so restricting the picker
 * to installed locales meant Manage Languages could offer almost nothing to activate, and
 * a template could not be translated into a language no client could be set to anyway.
 *
 * Nothing here switches the interface. The value is a note on the profile, and the one
 * thing that reads it is {@see \Paymenter\Extensions\Others\AdminOps\Models\TemplateLocale}
 * when it decides which version of an email to send.
 */
class Languages
{
    /**
     * Code => name, in the reference's order (its own, alphabetical by name).
     *
     * The two Portuguese entries are the reference's own split: Brazil and Portugal differ
     * in wording often enough that a store selling into both wants each written separately.
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
