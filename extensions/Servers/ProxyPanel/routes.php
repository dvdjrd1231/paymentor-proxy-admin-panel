<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Servers\ProxyPanel\Http\ProxyPanelController;
use Paymenter\Extensions\Servers\ProxyPanel\ProxyPanel;

// ── Panel → Paymenter ───────────────────────────────────────────────────────
// The panel posts status callbacks server-to-server (no CSRF token). The shared
// secret / HMAC check inside the handler is what authenticates the request.
Route::post('/extensions/proxypanel/callback', [ProxyPanel::class, 'callback'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.servers.proxypanel.callback');

// ── Country-flag webfont ────────────────────────────────────────────────────
// Region labels carry a flag emoji (see Support\CountryFlag). Windows ships no flag
// glyphs, so browsers there render the two country letters instead — the client asked
// for flags and would have seen "GB". This serves a small Twemoji subset containing only
// the regional-indicator range, which the theme applies via unicode-range so it affects
// nothing else. Served from here rather than public/ because public/ is not bind-mounted
// into the container, while extensions/ is.
Route::get('/extensions/proxypanel/flags.woff2', function () {
    $path = __DIR__ . '/resources/fonts/TwemojiCountryFlags.woff2';

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'font/woff2',
        // Immutable: the file only changes if the font itself is replaced.
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('extensions.servers.proxypanel.flagfont');

// ── Customer → Paymenter ────────────────────────────────────────────────────
// Core's service page can only call zero-argument extension functions, so the proxy
// management forms post here. `web` + `auth` give session, CSRF and login; the
// controller additionally authorizes each request against the Service policy.
Route::middleware(['web', 'auth'])
    ->prefix('extensions/proxypanel/services/{service}')
    ->name('extensions.servers.proxypanel.')
    ->group(function () {
        Route::get('export', [ProxyPanelController::class, 'export'])->name('export');
        Route::post('auth-ips', [ProxyPanelController::class, 'updateAuthIps'])->name('auth-ips');
        Route::post('password', [ProxyPanelController::class, 'updatePassword'])->name('password');
        Route::post('rotation', [ProxyPanelController::class, 'updateRotation'])->name('rotation');
    });
