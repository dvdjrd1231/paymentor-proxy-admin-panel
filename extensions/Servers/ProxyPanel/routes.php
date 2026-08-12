<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Servers\ProxyPanel\ProxyPanel;

// The proxy panel posts status callbacks server-to-server (no CSRF token). The shared
// secret / HMAC check inside the handler is what authenticates the request.
Route::post('/extensions/proxypanel/callback', [ProxyPanel::class, 'callback'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.servers.proxypanel.callback');
