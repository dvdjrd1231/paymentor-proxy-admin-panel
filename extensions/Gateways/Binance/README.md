# Binance Pay Gateway

Cryptocurrency payments via the official Binance Pay Merchant API (v3). HMAC-SHA512
signed orders, RSA-verified webhooks, idempotent settlement.

Full documentation: [`docs/modules/binance.md`](../../../docs/modules/binance.md).

**Enable:** enable **Binance Pay Gateway** under **Admin → Extensions**, then set API Key,
API Secret and Order Currency, and point the Binance webhook to
`https://YOUR-DOMAIN/extensions/binance/webhook`.
