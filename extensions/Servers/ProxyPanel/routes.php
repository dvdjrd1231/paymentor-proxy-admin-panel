<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Servers\ProxyPanel\Http\ProxyPanelController;
use Paymenter\Extensions\Servers\ProxyPanel\ProxyPanel;

// Panel → Paymenter. Server-to-server, so no CSRF token; the shared secret / HMAC check
// inside the handler is what authenticates it.
Route::post('/extensions/proxypanel/callback', [ProxyPanel::class, 'callback'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.servers.proxypanel.callback');

// Country-flag webfont. Windows ships no flag glyphs, so browsers there would render "GB"
// instead of 🇬🇧. This is a Twemoji subset of the regional-indicator range only, applied by
// the theme via unicode-range. Served from here because public/ is not bind-mounted into
// the container, while extensions/ is.
Route::get('/extensions/proxypanel/flags.woff2', function () {
    $path = __DIR__ . '/resources/fonts/TwemojiCountryFlags.woff2';

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'font/woff2',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->name('extensions.servers.proxypanel.flagfont');

// Customer → Paymenter. Core's service page can only call zero-argument extension
// functions, so the management forms post here. The controller authorizes each request
// against the Service policy on top of `web` + `auth`.
Route::middleware(['web', 'auth'])
    ->prefix('extensions/proxypanel/services/{service}')
    ->name('extensions.servers.proxypanel.')
    ->group(function () {
        Route::get('export', [ProxyPanelController::class, 'export'])->name('export');
        Route::post('auth-ips', [ProxyPanelController::class, 'updateAuthIps'])->name('auth-ips');
        Route::post('password', [ProxyPanelController::class, 'updatePassword'])->name('password');
        Route::post('rotation', [ProxyPanelController::class, 'updateRotation'])->name('rotation');
    });
