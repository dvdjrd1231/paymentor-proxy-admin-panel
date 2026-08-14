<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\Cryptomus\Cryptomus;

// Cryptomus posts webhooks server-to-server (no CSRF token). The `sign` check inside
// the handler is what authenticates the request.
Route::post('/extensions/cryptomus/webhook', [Cryptomus::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.gateways.cryptomus.webhook');

// Cryptomus verifies domain ownership by fetching a file it generates, from the site root:
//   https://<site>/cryptomus_<hash>.html
//
// `public/` is not a mounted volume in the Docker deployment, so a file dropped there is
// lost the next time the container is recreated. Serving it from the gateway's own setting
// keeps the whole thing inside this module and survives deploys — paste the downloaded
// file's contents into Admin → Gateways → Cryptomus.
Route::get('/{file}', [Cryptomus::class, 'domainVerification'])
    ->where('file', 'cryptomus_[A-Za-z0-9]+\.html')
    ->name('extensions.gateways.cryptomus.verification');
