<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\Binance\Binance;

// Binance Pay posts webhooks server-to-server (no CSRF token). The RSA signature
// check inside the handler is what authenticates the request.
Route::post('/extensions/binance/webhook', [Binance::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.gateways.binance.webhook');
