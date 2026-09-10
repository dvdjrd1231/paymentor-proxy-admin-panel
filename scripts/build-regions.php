<?php

/**
 * Rebuild extensions/Others/AdminOps/Support/regions-data.php from the published
 * ISO 3166-2 dataset, so the State/Region picker never depends on names typed by hand.
 *
 *   php scripts/build-regions.php
 *
 * The source is the country-regions/country-region-data project, which tracks the
 * standard. Run it only when the standard has actually moved — the committed file is
 * what ships, and this script needs network access.
 */
$source = 'https://raw.githubusercontent.com/country-regions/country-region-data/master/data.json';
$target = __DIR__ . '/../extensions/Others/AdminOps/Support/regions-data.php';

$json = file_get_contents($source);

if ($json === false) {
    fwrite(STDERR, "could not fetch {$source}\n");
    exit(1);
}

$rows = json_decode($json, true);

if (!is_array($rows)) {
    fwrite(STDERR, "unexpected payload\n");
    exit(1);
}

$out = [];

foreach ($rows as $country) {
    $code = $country['countryShortCode'] ?? null;

    if (!is_string($code) || !preg_match('/^[A-Z]{2}$/', $code)) {
        continue;
    }

    $names = [];

    foreach ($country['regions'] ?? [] as $region) {
        $name = trim((string) ($region['name'] ?? ''));

        if ($name !== '') {
            $names[] = $name;
        }
    }

    $names = array_values(array_unique($names));
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);

    if ($names !== []) {
        $out[$code] = $names;
    }
}

ksort($out);

$php = "<?php\n\n"
    . "// Generated from the public ISO 3166-2 dataset (country-regions/country-region-data),\n"
    . "// which is the published standard rather than anything hand-typed. Regenerate with\n"
    . "// scripts/build-regions.php if the standard changes.\n\n"
    . "return [\n";

foreach ($out as $code => $names) {
    $php .= '    ' . var_export($code, true) . ' => ['
        . implode(', ', array_map(fn (string $n): string => var_export($n, true), $names))
        . "],\n";
}

$php .= "];\n";

file_put_contents($target, $php);

printf(
    "wrote %s — %d countries, %d subdivisions\n",
    basename($target),
    count($out),
    array_sum(array_map('count', $out)),
);
