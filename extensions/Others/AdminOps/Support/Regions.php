<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use Paymenter\Extensions\Servers\ProxyPanel\Support\CountryFlag;

/**
 * The first-level subdivisions a country is addressed by, so State/Region can be picked
 * from a list rather than typed — the reference's own behaviour (Leandro's screenshot,
 * 2026-09-10: choosing United States lists Alabama, Alaska, Arizona…).
 *
 * Every country is covered. `regions-data.php` beside this file holds all 249 of them and
 * their 4,387 subdivisions, generated from the published ISO 3166-2 dataset rather than
 * typed out here — a hand-kept list is exactly how a wrong subdivision name reaches an
 * invoice. Regenerate it with `scripts/build-regions.php` if the standard moves.
 *
 * A country the standard gives no subdivisions for keeps the free-text box, which is the
 * honest answer for one.
 */
class Regions
{
    /** @var array<string, array<int, string>>|null loaded once per request */
    private static ?array $map = null;

    /**
     * The regions for a country named as the Country select spells it ("United States"),
     * or an empty array when the field should stay free text.
     *
     * @return array<int, string>
     */
    public static function for(?string $country): array
    {
        $country = trim((string) $country);

        if ($country === '') {
            return [];
        }

        // Reuse ProxyPanel's name -> ISO index rather than keeping a second country list.
        $code = (class_exists(CountryFlag::class) ? CountryFlag::codeFor($country) : null)
            ?? strtoupper($country);

        return static::map()[$code] ?? [];
    }

    /** @return array<string, array<int, string>> */
    private static function map(): array
    {
        return self::$map ??= (array) require __DIR__ . '/regions-data.php';
    }
}
