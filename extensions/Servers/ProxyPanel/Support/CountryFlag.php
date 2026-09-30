<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Support;

/**
 * Turns a "Country - City" region label into a flag-prefixed one:
 * "United States - Kansas City" -> "🇺🇸  United States - Kansas City".
 */
class CountryFlag
{
    /**
     * Spellings that differ from Paymenter's country list. Keys are lower-cased.
     */
    private const ALIASES = [
        'usa' => 'US',
        'u.s.' => 'US',
        'u.s.a.' => 'US',
        'united states of america' => 'US',
        'uk' => 'GB',
        'u.k.' => 'GB',
        'great britain' => 'GB',
        'england' => 'GB',
        'scotland' => 'GB',
        'wales' => 'GB',
        'uae' => 'AE',
        'emirates' => 'AE',
        'south korea' => 'KR',
        'korea' => 'KR',
        'north korea' => 'KP',
        'russia' => 'RU',
        'vietnam' => 'VN',
        'viet nam' => 'VN',
        'czechia' => 'CZ',
        'czech republic' => 'CZ',
        'holland' => 'NL',
        'the netherlands' => 'NL',
        'turkey' => 'TR',
        'türkiye' => 'TR',
        'ivory coast' => 'CI',
        'cape verde' => 'CV',
        'hong kong sar' => 'HK',
        'macau' => 'MO',
        'bolivia' => 'BO',
        'venezuela' => 'VE',
        'iran' => 'IR',
        'syria' => 'SY',
        'laos' => 'LA',
        'moldova' => 'MD',
        'tanzania' => 'TZ',
        'brunei' => 'BN',
    ];

    /** name (lower-cased) => ISO-3166 alpha-2, built once per request. */
    private static ?array $byName = null;

    /** Prefix a label with its flag, or return it unchanged if the country is unknown. */
    public static function decorate(string $label): string
    {
        $flag = self::forLabel($label);

        return $flag ? $flag . ' ' . $label : $label;
    }

    /** The flag emoji for a "Country - City" label, or null if not recognised. */
    public static function forLabel(string $label): ?string
    {
        // Everything before the first dash is the country ("United States - Kansas City").
        $country = trim(preg_split('/\s[-–—]\s/u', $label, 2)[0] ?? '');

        if ($country === '') {
            return null;
        }

        $code = self::codeFor($country);

        return $code ? self::marker($code) : null;
    }

    /** ISO-3166 alpha-2 for a country name, or null. */
    public static function codeFor(string $country): ?string
    {
        $key = mb_strtolower(trim($country));

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        return self::nameIndex()[$key] ?? null;
    }

    /**
     * The country marker shown before a region's name.
     *
     * Plain uppercase letters, not the flag emoji {@see emoji()} builds. Windows ships no
     * glyphs for regional indicators, so a browser there falls back to drawing the two
     * letters in whatever font it can find — tiny small-capitals against the label beside
     * them. That is what "ɪᴅ Indonesia - Jakarta" was, and why Leandro reported the region
     * list as not legible twice (#10, 2026-09-02 and 2026-09-03). The same two letters in
     * the page's own font are the same information and readable on every platform.
     *
     * A real flag needs artwork — an image or a font carrying the glyphs — and the region
     * picker renders labels as text with no markup allowed through, so that is a change of
     * shape rather than of spelling.
     */
    public static function marker(string $iso2): string
    {
        $iso2 = strtoupper(trim($iso2));

        return preg_match('/^[A-Z]{2}$/', $iso2) ? $iso2 : '';
    }
    /** Each letter becomes its REGIONAL INDICATOR SYMBOL (U+1F1E6 = 'A'); the pair renders as one flag. */
    public static function emoji(string $iso2): string
    {
        $iso2 = strtoupper(trim($iso2));

        if (!preg_match('/^[A-Z]{2}$/', $iso2)) {
            return '';
        }

        $flag = '';
        foreach (str_split($iso2) as $letter) {
            $flag .= mb_chr(0x1F1E6 + (ord($letter) - ord('A')), 'UTF-8');
        }

        return $flag;
    }

    /** Reverse of Paymenter's country list: lower-cased name => code. */
    private static function nameIndex(): array
    {
        if (self::$byName !== null) {
            return self::$byName;
        }

        self::$byName = [];

        foreach ((array) config('app.countries', []) as $code => $name) {
            if (!preg_match('/^[A-Z]{2}$/', (string) $code)) {
                continue;   // skips the '' => 'Select a country' placeholder
            }
            self::$byName[mb_strtolower((string) $name)] = $code;
        }

        return self::$byName;
    }
}
