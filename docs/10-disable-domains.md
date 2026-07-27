# Disable Domain Sales (spec item 10)

**Requirement:** the company does not sell domain names; all domain-related
functionality must be permanently disabled without affecting future Paymenter upgrades.

## Status: satisfied by architecture — nothing to remove

Paymenter (this build, `master` / v1.5.x) has **no domain-selling functionality at
all**, so there is nothing to strip out and nothing that can regress on upgrade.
Verified against the vendored source:

| Check | Result |
|---|---|
| `Domain` / `Registrar` Eloquent model | **none** (`app/Models/` has Product, Service, Order, Invoice, Category, Plan… only) |
| Registrar **extension type** | **none** — only `Gateways`, `Servers`, `Others` exist |
| Domain **product type** on `Product` / `Plan` | **none** — products are services provisioned by a `Server` module |
| Domain UI (search/register/transfer, TLD pricing) | **none** |
| WHMCS importer handling of domains | `app/Console/Commands/ImportFromWhmcs.php` maps `'Domain' => null` — domain products are **skipped**, not imported |

The only occurrence of the word "domain" in application code is `app/Rules/Domain.php`,
a **validation rule** that checks a string is a valid hostname (used for server/host
config fields) — it has nothing to do with selling domains.

## Why this is upgrade-safe

Because we do **not** edit core to remove a domain feature (there is none), there is no
patch to lose on `git merge upstream/*`. Future Paymenter releases would have to *add* a
domain system before any domain UI could appear — see "Keeping it disabled" below.

## Keeping it disabled

Operational guardrails so domains never appear for this business:

1. **Do not install** any future domain/registrar extension. Only these extensions are
   enabled for this deployment (see `docs/AUTHORED-FILES.md`): the proxy `Server` module,
   the payment gateways, and the `Others/*` mini-apps. No registrar type exists to enable.
2. **Product catalog** contains only proxy service products (each tied to the
   `Servers/ProxyPanel` module). No product represents a domain.
3. **If a future upstream release adds a domain module**, this doc is the checkpoint:
   after upgrading, confirm no "Domains"/registrar navigation appears in the admin panel
   and, if it does, leave that module disabled. Record the decision in
   `docs/CORE-TOUCHPOINTS.md` only if a config/gate is ever required.

## Verification

```bash
# No domain/registrar model or extension type should exist:
grep -rniE "class .*Domain extends Model|Registrar" app/Models extensions || echo "none — OK"
# Products are service products only (spot-check in admin: Catalog → Products).
```

No customer-facing domain search, cart line, or invoice item is reachable, because no
code path produces one.
