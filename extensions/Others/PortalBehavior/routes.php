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
