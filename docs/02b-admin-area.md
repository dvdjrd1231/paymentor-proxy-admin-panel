# Administrative Area Customization (spec item 2)

The brief asks for an admin area **optimized for daily operations**, with improved
organization of clients / products / services / payments / tickets and an
**administrative dashboard with operational metrics**.

## What Paymenter's admin already provides

Paymenter's admin is a modern **Filament 5** panel — not a legacy table UI. Out of the
box it already delivers most of item 2:

- **Organized navigation groups** — clients, catalog (products/categories), services,
  billing (invoices/transactions/coupons), support (tickets), configuration.
- **Dashboard with metrics** — core widgets: Revenue, Support overview, Active users,
  Cron/scheduler status.
- **Rich resources** — searchable, filterable tables with bulk actions and policies for
  every core model.

So the work here is **branding + a proxy-business metrics widget**, not rebuilding the
admin.

## Branding — already settings-driven (no code)

The admin panel reads branding from **Admin → Settings**:

| Setting | Effect on admin |
|---|---|
| **Logo** (`logo`) | Admin brand logo (light) |
| **Dark logo** (`logo_dark`) | Admin brand logo (dark mode) |
| **Favicon** | Browser tab icon |
| **App name** | Brand name shown when no logo is set |

Upload your **NoxProxy** logo and set the app name once, and the admin panel picks them
up automatically — no code changes.

### Admin primary colour (optional)

The admin accent colour is set in `app/Providers/Filament/AdminPanelProvider.php`
(`->colors(['primary' => Color::Blue])`). To match the brand, change it to
`Color::hex('#e8365d')`. This is a small, optional core edit documented in
`docs/CORE-TOUCHPOINTS.md` #3 (cosmetic; skip if you prefer to keep core untouched).

## Operations metrics widget (this project)

`Others/AdminOps` adds an **Operations** `StatsOverviewWidget` tuned to the proxy
business:

- Active services · Suspended (need attention) · Open tickets · Unpaid invoices
- Paid invoices (30d) · New customers (30d)

Modeled on Paymenter's own dashboard widgets, colour-coded (warning/danger when a
number needs attention), auto-refreshing every 120s.

### Enable

1. Enable **AdminOps** under Admin → Extensions.
2. Paymenter auto-discovers extension **Resources/Pages** but not **Widgets**, so add the
   one-line widget-discovery in `AdminPanelProvider` — see `docs/CORE-TOUCHPOINTS.md` #3.
   The Operations widget then appears on the admin dashboard.

> Prefer zero core edits? The widget can instead be hosted on a dedicated Filament admin
> page (auto-discovered) — ask and it can be delivered that way.

## Daily-operations tips

- **Assign tickets** to staff and use **Canned Responses** + **Internal Notes**
  (`Others/TicketTools`) for faster support.
- **Payment Fee Rules** and **Gateway Rules** live under **Configuration**.
- Provisioning actions (suspend/unsuspend/terminate/sync) are on each **Service**.
