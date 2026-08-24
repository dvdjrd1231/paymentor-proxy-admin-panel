<?php

use App\Classes\Cart;
use Illuminate\Support\Facades\Route;

// Empty Cart — core's Cart component only exposes removeProduct($index), and wire:click
// calls one method, so a button bound to it would drop just the first line. POST behind
// `web` for CSRF: a GET would let any page empty a visitor's cart with an <img> tag.
Route::post('/cart/empty', function () {
    Cart::clear();

    return back();
})->middleware('web')->name('extensions.others.portal.cart.empty');

// Leave the cart to sign in, and come back to it. Core bounces a guest who presses Checkout
// to a login page that offers no way to register and no way back. Recording `url.intended`
// here means both login and registration return the buyer to the cart.
Route::get('/cart/continue/{to}', function (string $to) {
    abort_unless(in_array($to, ['login', 'register'], true), 404);

    redirect()->setIntendedUrl(route('cart'));

    return redirect()->route($to);
})->middleware('web')->name('cart.continue');

// The theme's design system and its two webfonts.
//
// These live in the extension, not public/, because public/ is not bind-mounted into the
// container while extensions/ and themes/ are — assets dropped in public/ work locally and
// 404 on the server. Themes cannot register routes of their own.
//
// All three are immutable for a year; the stylesheet is busted by the ?v= content hash the
// theme appends. Serving the CSS as a file rather than inlining it cut the login page from
// 83KB to 20KB and made it cacheable.
Route::get('/extensions/portal/theme.css', function () {
    $path = base_path('themes/proxy/assets/whmcs.css');

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'text/css; charset=UTF-8',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('extensions.others.portal.css');

// Open Sans and Raleway are shipped rather than fetched from a font CDN: no third-party
// request per page load, and they keep working offline and under a strict CSP. One variable
// file covers every weight.
Route::get('/extensions/portal/opensans.woff2', function () {
    $path = __DIR__ . '/resources/fonts/OpenSans-Variable.woff2';

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'font/woff2',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('extensions.others.portal.font');

Route::get('/extensions/portal/raleway.woff2', function () {
    $path = __DIR__ . '/resources/fonts/Raleway-Variable.woff2';

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'font/woff2',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('extensions.others.portal.font.heading');
