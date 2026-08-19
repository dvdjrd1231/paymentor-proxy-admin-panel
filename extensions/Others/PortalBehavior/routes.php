<?php

use Illuminate\Support\Facades\Route;

// ── Theme webfont ───────────────────────────────────────────────────────────
// The reference portal is set in Open Sans, so the theme ships it rather than pulling from
// a font CDN: no third-party request on every page load, and it keeps working offline and
// behind a strict CSP. One variable file covers weights 300-800, which is why there is a
// single 48KB request instead of four.
//
// Served from the extension because public/ is not bind-mounted into the container while
// extensions/ is — a font dropped in public/ works locally and 404s on the server.
Route::get('/extensions/portal/opensans.woff2', function () {
    $path = __DIR__ . '/resources/fonts/OpenSans-Variable.woff2';

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'font/woff2',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('extensions.others.portal.font');
