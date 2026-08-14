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
