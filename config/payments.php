<?php

/*
|--------------------------------------------------------------------------
| Payment credential mode
|--------------------------------------------------------------------------
|
| PAYMENT_MODE=dev   → gateways use the development / sandbox credentials
|                      listed below, taken from .env.
| PAYMENT_MODE=prod  → gateways use the credentials stored in the database
|                      (Admin → Gateways), i.e. the client's real keys.
|
| The default is "prod", so a deployment that never sets PAYMENT_MODE keeps
| using the credentials an admin entered — dev keys are strictly opt-in.
|
| Only keys with a value take effect. Anything left blank falls through to
| the database value, so you can override a single field (say, a webhook
| secret for `stripe listen`) without duplicating the whole gateway.
|
| These are read through config() rather than env() directly so they keep
| working after `php artisan config:cache`, where .env is no longer loaded.
|
| Development credentials live in .env, which is gitignored — never commit
| them. See docs/PAYMENT-KEYS.md for where each value comes from.
|
*/

return [

    'mode' => env('PAYMENT_MODE', 'prod'),

    /*
    | Keyed by extension name, then by the gateway's own setting name — the
    | same names shown in Admin → Gateways.
    */
    'dev' => [

        'Stripe' => [
            'stripe_secret_key' => env('PAYMENT_DEV_STRIPE_SECRET_KEY'),
            'stripe_publishable_key' => env('PAYMENT_DEV_STRIPE_PUBLISHABLE_KEY'),
            'stripe_webhook_secret' => env('PAYMENT_DEV_STRIPE_WEBHOOK_SECRET'),
        ],

        'CoinPayments' => [
            'client_id' => env('PAYMENT_DEV_COINPAYMENTS_CLIENT_ID'),
            'client_secret' => env('PAYMENT_DEV_COINPAYMENTS_CLIENT_SECRET'),
            'api_url' => env('PAYMENT_DEV_COINPAYMENTS_API_URL'),
        ],

        'Cryptomus' => [
            'merchant_id' => env('PAYMENT_DEV_CRYPTOMUS_MERCHANT_ID'),
            'payment_api_key' => env('PAYMENT_DEV_CRYPTOMUS_PAYMENT_API_KEY'),
        ],

        'Binance' => [
            'api_key' => env('PAYMENT_DEV_BINANCE_API_KEY'),
            'api_secret' => env('PAYMENT_DEV_BINANCE_API_SECRET'),
            'pay_currency' => env('PAYMENT_DEV_BINANCE_PAY_CURRENCY'),
            'test_mode' => env('PAYMENT_DEV_BINANCE_TEST_MODE'),
            'sandbox_url' => env('PAYMENT_DEV_BINANCE_SANDBOX_URL'),
        ],

    ],

];
