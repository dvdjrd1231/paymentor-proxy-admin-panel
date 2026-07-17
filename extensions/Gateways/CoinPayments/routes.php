<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\CoinPayments\CoinPayments;

// CoinPayments posts IPNs server-to-server (no CSRF token). Signature validation
// inside the handler is what authenticates the request.
Route::post('/extensions/coinpayments/ipn', [CoinPayments::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.gateways.coinpayments.ipn');
