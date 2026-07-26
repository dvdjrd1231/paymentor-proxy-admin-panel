# Country-Based Gateway Rules

Control which payment gateways appear at checkout based on customer country, product,
product group, customer type, currency and amount. Enforced server-side.

Full documentation: [`docs/modules/gateway-rules.md`](../../../docs/modules/gateway-rules.md).

**Enable:** Admin → Extensions → GatewayRules, then manage rules under
**Admin → Configuration → Gateway Rules**.

> Our gateways enforce rules natively via `canUseGateway()`. To enforce for all
> gateways, apply the optional filter in [`docs/CORE-TOUCHPOINTS.md`](../../../docs/CORE-TOUCHPOINTS.md) #2.
