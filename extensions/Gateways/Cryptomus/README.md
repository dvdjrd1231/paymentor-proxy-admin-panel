# Cryptomus Gateway

Cryptocurrency payments via Cryptomus (official API). Signed requests, sign-verified
webhooks, idempotent settlement.

Full documentation: [`docs/modules/cryptomus.md`](../../../docs/modules/cryptomus.md).

**Enable:** `php artisan app:extension:enable Gateways/Cryptomus`, then set Merchant UUID
+ Payment API Key, and point the Cryptomus callback to
`https://YOUR-DOMAIN/extensions/cryptomus/webhook`.
