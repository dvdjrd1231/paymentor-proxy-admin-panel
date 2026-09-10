<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use Paymenter\Extensions\Servers\ProxyPanel\Support\CountryFlag;

/**
 * The first-level subdivisions a country is addressed by, so State/Region can be picked
 * from a list rather than typed — the reference's own behaviour (Leandro's screenshot,
 * 2026-09-10: choosing United States lists Alabama, Alaska, Arizona…).
 *
 * Only the countries this store actually addresses are listed. Everywhere else the field
 * stays free text, which is the honest answer: inventing a half-remembered list for a
 * country nobody orders from would put wrong names in front of the person typing, and a
 * subdivision typed by hand is better than one picked from a bad list.
 */
class Regions
{
    /** ISO-3166-1 alpha-2 => subdivision names, alphabetical as the reference lists them. */
    private const MAP = [
        'US' => [
            'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut',
            'Delaware', 'District of Columbia', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois',
            'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland',
            'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana',
            'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico', 'New York',
            'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania',
            'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah',
            'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
        ],
        // The 26 states and the Distrito Federal, written as a Brazilian invoice needs them.
        'BR' => [
            'Acre', 'Alagoas', 'Amapá', 'Amazonas', 'Bahia', 'Ceará', 'Distrito Federal',
            'Espírito Santo', 'Goiás', 'Maranhão', 'Mato Grosso', 'Mato Grosso do Sul',
            'Minas Gerais', 'Pará', 'Paraíba', 'Paraná', 'Pernambuco', 'Piauí',
            'Rio de Janeiro', 'Rio Grande do Norte', 'Rio Grande do Sul', 'Rondônia',
            'Roraima', 'Santa Catarina', 'São Paulo', 'Sergipe', 'Tocantins',
        ],
        'CA' => [
            'Alberta', 'British Columbia', 'Manitoba', 'New Brunswick',
            'Newfoundland and Labrador', 'Northwest Territories', 'Nova Scotia', 'Nunavut',
            'Ontario', 'Prince Edward Island', 'Quebec', 'Saskatchewan', 'Yukon',
        ],
        'AU' => [
            'Australian Capital Territory', 'New South Wales', 'Northern Territory',
            'Queensland', 'South Australia', 'Tasmania', 'Victoria', 'Western Australia',
        ],
        'GB' => ['England', 'Northern Ireland', 'Scotland', 'Wales'],
        'PT' => [
            'Aveiro', 'Beja', 'Braga', 'Bragança', 'Castelo Branco', 'Coimbra', 'Évora',
            'Faro', 'Guarda', 'Leiria', 'Lisboa', 'Portalegre', 'Porto', 'Santarém',
            'Setúbal', 'Viana do Castelo', 'Vila Real', 'Viseu', 'Açores', 'Madeira',
        ],
    ];

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
        $code = class_exists(CountryFlag::class) ? CountryFlag::codeFor($country) : null;

        return self::MAP[$code] ?? self::MAP[strtoupper($country)] ?? [];
    }
}
