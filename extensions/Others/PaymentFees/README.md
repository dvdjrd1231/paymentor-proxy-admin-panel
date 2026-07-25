# Payment Method Fees

Charge an extra fee based on the selected payment gateway — fixed / percentage /
fixed+percentage — scoped by country, currency, product, customer type and invoice
amount. All calculation is server-side.

Full documentation: [`docs/modules/payment-fees.md`](../../../docs/modules/payment-fees.md).

**Enable:** Admin → Extensions → PaymentFees, then manage rules under
**Admin → Configuration → Payment Fee Rules**.

> Applying the fee needs one small, documented call in the invoice payment component —
> see [`docs/CORE-TOUCHPOINTS.md`](../../../docs/CORE-TOUCHPOINTS.md).
