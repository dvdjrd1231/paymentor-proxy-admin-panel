<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\Cryptomus\Cryptomus;

// Cryptomus posts webhooks server-to-server (no CSRF token). The `sign` check inside
// the handler is what authenticates the request.
Route::post('/extensions/cryptomus/webhook', [Cryptomus::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.gateways.cryptomus.webhook');
