# CoinPayments Gateway

Cryptocurrency payment gateway for Paymenter (CoinPayments API + IPN, HMAC-SHA512, idempotent).

Full documentation: [`docs/modules/coinpayments.md`](../../../docs/modules/coinpayments.md).

**Enable:** enable **CoinPayments Gateway** under **Admin → Extensions**, then create a Gateway and set
Merchant ID, API public/private keys, IPN Secret, and Receive Currency.

**IPN URL:** `https://YOUR-DOMAIN/extensions/coinpayments/ipn` (IPN Mode = HMAC).
