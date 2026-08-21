<?php

use App\Classes\Cart;
use Illuminate\Support\Facades\Route;

// ── Empty Cart ──────────────────────────────────────────────────────────────
// The reference portal's cart has a single "Empty Cart" button. Paymenter's Cart Livewire
// component only exposes removeProduct($index), and wire:click calls exactly one method,
// so a button bound to it would silently drop just the first line — worse than not having
// the button. This clears the whole cart in one step without touching core.
//
// POST, and behind the web middleware group, so it carries CSRF protection: a GET would let
// any third-party page empty a visitor's cart with an <img> tag. The cart lives in the
// session and holds no money, so no auth is required — guests build carts before logging in.
Route::post('/cart/empty', function () {
    Cart::clear();

    return back();
})->middleware('web')->name('extensions.others.portal.cart.empty');

// ── Theme stylesheet ────────────────────────────────────────────────────────
// The proxy theme's design system, ~60KB of static CSS.
//
// It used to be inlined into every page by layouts/whmcs-css.blade.php, which made it 76%
// of each response (63KB of an 83KB login page), re-sent on every request and impossible
// for a browser to cache. Serving it as a file means one download and a 304 thereafter.
//
// The route lives here rather than in the theme for two reasons: a theme cannot register
// routes, and public/ is not bind-mounted into the container while themes/ is — a
// stylesheet dropped in public/ works locally and 404s on the server, exactly as noted for
// the fonts below.
//
// Cached immutable for a year and busted by the ?v= content hash the theme appends, so an
// edit is picked up immediately and an unchanged file is never re-fetched.
Route::get('/extensions/portal/theme.css', function () {
    $path = base_path('themes/proxy/assets/whmcs.css');

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'text/css; charset=UTF-8',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('extensions.others.portal.css');

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

// Raleway carries the reference portal's menu bar and page headings, and is shipped the
// same way and for the same reasons as Open Sans above.
Route::get('/extensions/portal/raleway.woff2', function () {
    $path = __DIR__ . '/resources/fonts/Raleway-Variable.woff2';

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'font/woff2',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('extensions.others.portal.font.heading');
