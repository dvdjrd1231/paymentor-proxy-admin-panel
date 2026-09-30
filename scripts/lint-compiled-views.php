<?php

/**
 * Lint every compiled Blade view under extensions/.
 *
 * `view:cache` compiles Blade to PHP without checking that the PHP it produced parses, so a
 * broken view caches "successfully" and only fails when someone opens the page. One did:
 * an inline `@php (...)` paired with a later `@endphp` and swallowed the markup between,
 * taking the client summary down for every client. This runs at deploy time so the next one
 * is caught before it ships.
 */

$root = base_path('extensions');
$failed = [];
$checked = 0;

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

foreach ($files as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $source = file_get_contents($file->getPathname());

    // An XML view opens with `<?xml version=...`, which php -l reads as a short open tag
    // and rejects. Nothing to do with the template being wrong.
    if (str_starts_with(ltrim($source), '<?xml')) {
        continue;
    }

    $checked++;
    $tmp = tempnam(sys_get_temp_dir(), 'bladelint') . '.php';

    try {
        file_put_contents($tmp, app('blade.compiler')->compileString($source));
    } catch (Throwable $e) {
        $failed[] = $file->getPathname() . ' — ' . $e->getMessage();
        @unlink($tmp);

        continue;
    }

    $output = [];
    exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        // Report against the source, not the throwaway compiled path.
        $failed[] = $file->getPathname() . ' — ' . trim(str_replace($tmp, 'compiled output', implode(' ', $output)));
    }

    @unlink($tmp);
}

printf("Checked %d Blade views.\n", $checked);

if ($failed) {
    echo "These do not compile to valid PHP:\n";
    foreach ($failed as $line) {
        echo '  - ' . $line . "\n";
    }

    exit(1);
}

echo "All compile.\n";
