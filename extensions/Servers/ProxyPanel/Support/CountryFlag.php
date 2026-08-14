<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Support;

/**
 * Turns a "Country - City" region label into a flag-prefixed label.
 *
 *   "United States - Kansas City"  ->  "🇺🇸  United States - Kansas City"
 *
 * The country name is matched against Paymenter's own ISO-3166 list
 * (`config('app.countries')`, 249 entries) so there is no second list to maintain,
 * plus a small alias table for the spellings panels commonly use.
 *
 * The flag itself is two Unicode regional-indicator symbols — the only way to show a
 * flag inside a native <select>, which cannot contain images.
 *
 * ⚠ Windows renders these as the two letters ("US") rather than a flag: it ships no
 * flag glyphs, so Chrome, Edge and Firefox on Windows all fall back to the letters.
 * macOS, iOS, Android and most Linux desktops show the flag. Nothing can change that
 * from the server side short of replacing the native select with an image-based
 * custom dropdown.
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

    /**
     * Prefix a "Country - City" label with its flag. Returns the label unchanged when
     * the country cannot be identified — never guesses, and never loses the label.
     */
    public static function decorate(string $label): string
    {
        $flag = self::forLabel($label);

        // Two spaces read better than one: the flag glyph is visually wide.
        return $flag ? $flag . '  ' . $label : $label;
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

        return $code ? self::emoji($code) : null;
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
     * Convert an ISO-3166 alpha-2 code into its flag: each letter becomes the matching
     * REGIONAL INDICATOR SYMBOL (U+1F1E6 is 'A'), and the pair renders as one flag.
     */
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
