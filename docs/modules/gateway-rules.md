# Country-Based Gateway Rules

Spec item 5: control which payment gateways are available at checkout based on
**customer country, product, product group (category), customer type, currency and
invoice/cart amount**. Enforced **on the backend**.

- **Location:** `extensions/Others/GatewayRules/`
- **Type:** Others (model, migration, Filament admin, decision engine)

## How it works

Admins create **Gateway Rules** (Admin → Configuration → *Gateway Rules*). Each rule
targets a gateway (or *any*), has a **mode** — `allow` or `deny` — and a set of
conditions. When Paymenter builds the list of gateways a customer can pay with,
`GatewayRuleEngine::allows()` decides each gateway server-side:

- Rules are evaluated by ascending `priority` (then id).
- The **first active rule whose conditions all match wins**; its `mode` is the answer
  (`allow` → shown, `deny` → hidden).
- If **no rule matches**, the gateway is **available** (default allow).

### Conditions (blank = ignore)

| Field | Matches when |
|---|---|
| Gateway | rule gateway empty, or equals the gateway (`extension` name) |
| Country | customer `country` property equals the rule (ISO-2 or name, case-insensitive) |
| Currency | checkout currency equals the rule |
| Product | any item's product id equals the rule |
| Product group | any item's category id equals the rule |
| Customer type | `business` if the customer has a company name / CNPJ, else `individual` |
| Min / Max amount | total is within the range |

### Examples

- *"Only offer PIX/Cryptomus to Brazil"* → deny that gateway with **country ≠ BR**…
  actually model it as: a `deny` rule (country = *each non-BR*) is clumsy; instead add an
  `allow` rule (gateway=Cryptomus, country=BR, priority 10) and a `deny` rule
  (gateway=Cryptomus, priority 20). First match wins: BR → allow, others → deny.
- *"Hide PayPal for invoices over 1000 USD"* → `deny`, gateway=PayPal, currency=USD,
  min_amount=1000.

## Enforcement points

- **Our gateways** (CoinPayments, Binance) call `GatewayRules::allows()` from their
  native `canUseGateway()` hook — enforced with **no core change**.
- **All gateways** (incl. Stripe, Cryptomus): one optional central filter in
  `getCheckoutGateways()` — see [`docs/CORE-TOUCHPOINTS.md`](../CORE-TOUCHPOINTS.md) #2.

## Files

- `Models/GatewayRule.php`, `database/migrations/*_create_gateway_rules_table.php`
- `Support/GatewayRuleEngine.php` — server-side decision (no I/O, testable)
- `Admin/Resources/GatewayRuleResource.php` (+ Pages) — admin CRUD (auto-discovered)
- `GatewayRules.php` — extension: `allows()`

## Enable

```
Admin → Extensions → GatewayRules     (runs the migration)
Admin → Configuration → Gateway Rules (create rules)
```

## Security

All decisions are computed server-side from the invoice/cart and the customer's stored
country/type — never trusted from the browser. A hidden gateway is also rejected on the
pay action, because Paymenter re-checks availability before charging.
