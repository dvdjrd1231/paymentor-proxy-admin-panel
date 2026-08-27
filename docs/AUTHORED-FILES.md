# Authored files (this project vs. upstream Paymenter)

Everything **not** in this list is unmodified upstream Paymenter (vendored — see
`README.paymenter-upstream.md`). Custom, project-authored files:

## Payment gateways
- `extensions/Gateways/CoinPayments/CoinPayments.php` — CoinPayments gateway (API + IPN)
- `extensions/Gateways/CoinPayments/routes.php` — IPN webhook route
- `extensions/Gateways/CoinPayments/resources/views/pay.blade.php` — checkout view
- `extensions/Gateways/CoinPayments/README.md`

## Provisioning
- `extensions/Servers/ProxyPanel/ProxyPanel.php` — native proxyPanel provisioning module
- `extensions/Servers/ProxyPanel/Support/PanelApi.php` — panel API client for admin pages (locations, tunnels)
- `extensions/Servers/ProxyPanel/Admin/Pages/PanelLocations.php` — Admin → Panel → Locations console
- `extensions/Servers/ProxyPanel/resources/views/admin/` — console page + location detail modal
- `extensions/Servers/ProxyPanel/README.md`

## Customer registration
- `extensions/Others/BrazilianRegistration/BrazilianRegistration.php` — CPF/CNPJ fields, validation, encryption, masks
- `extensions/Others/BrazilianRegistration/Support/Documents.php` — CPF/CNPJ checksum + masking helpers
- `extensions/Others/BrazilianRegistration/database/migrations/2026_07_17_000000_seed_brazilian_custom_properties.php`
- `extensions/Others/BrazilianRegistration/resources/views/masks.blade.php` — input-mask script
- `extensions/Others/BrazilianRegistration/README.md`
- `docs/modules/brazilian-registration.md`

## Admin area (WHMCS-style usability — spec item 2)
- `extensions/Others/AdminOps/AdminOps.php` — widget styles, sidebar service/cancellation queues
- `extensions/Others/AdminOps/Admin/Widgets/HeadlineTiles.php` — the reference's four homepage tiles
- `extensions/Others/AdminOps/resources/views/rail.blade.php` — the WHMCS left sidebar column
- `extensions/Others/AdminOps/Admin/Widgets/AtAGlance.php` — income/services/customers/tickets by period
- `extensions/Others/AdminOps/Admin/Widgets/ActionQueue.php` — "Needs attention" work queue
- `extensions/Others/AdminOps/Admin/Widgets/WhoIsAround.php` — staff online + client activity
- `extensions/Others/AdminOps/Admin/Pages/ClientSummary.php` — one-screen customer view + log in as customer
- `extensions/Others/AdminOps/Support/WhmcsNavigation.php` — the WHMCS menu bar over Paymenter's resources
- `extensions/Others/AdminOps/Support/Toolbar.php` — the `+` and utility icons at each end of that bar
- `extensions/Others/AdminOps/Admin/Auth/Login.php` — the admin sign-in page, on Paymenter's auth stack, with the client login's CAPTCHA
- `extensions/Others/AdminOps/resources/views/captcha.blade.php` — that CAPTCHA, for Filament's sign-in card
- `extensions/Others/AdminOps/Admin/Pages/Catalogue.php` — Products/Services: groups and products ordered by dragging
- `extensions/Others/AdminOps/Admin/Widgets/DashboardTools.php` — dashboard panels: drag, collapse, refresh, hide
- `extensions/Others/AdminOps/Admin/Pages/AutomationStatus.php` — is the automation running, and what did it do
- `extensions/Others/AdminOps/Models/DashboardLayout.php` — one admin's dashboard order and hidden panels
- `extensions/Others/AdminOps/Support/PanelSession.php` — issues the session row any sign-in path missed
- `extensions/Servers/ProxyPanel/Support/PanelHttpException.php` — panel HTTP failures, carrying the status
- `extensions/Others/AdminOps/Support/Rail.php` — data for the WHMCS left sidebar column
- `extensions/Others/AdminOps/Support/Metrics.php` — every dashboard figure, defined once
- `extensions/Others/AdminOps/Support/Money.php` — multi-currency-safe money formatting
- `extensions/Others/AdminOps/Support/Links.php` — cross-extension admin URLs
- `extensions/Others/AdminOps/resources/views/` — widget/page templates and their stylesheet
- `extensions/Others/AdminOps/README.md`
- `docs/02b-admin-area.md`

## Fixed-term products (daily/weekly)
- `extensions/Others/TermLimits/TermLimits.php` — the extension: starts the clock, schedules the sweeper
- `extensions/Others/TermLimits/Support/Terms.php` — open, extend, close a term
- `extensions/Others/TermLimits/Support/Sweeper.php` — one pass over what is due, and the backfill
- `extensions/Others/TermLimits/Console/EnforceTerms.php` — `term-limits:enforce`
- `extensions/Others/TermLimits/Models/` — `ServiceTerm`, `ServiceTermExtension`, `ProductTerm`
- `extensions/Others/TermLimits/Admin/Resources/ProductTermResource.php` — Setup → Auto Terminate, the reference's per-product field
- `extensions/Others/TermLimits/Admin/Resources/ServiceTermResource.php` — Orders → Fixed Terms, with Extend
- `extensions/Others/TermLimits/database/migrations/` — `ext_term_limits`, `ext_term_limit_extensions`
- `extensions/Others/TermLimits/README.md`
- `docs/modules/term-limits.md`

## Cancellation requests
- `extensions/Others/Cancellations/Cancellations.php` — acts on immediate requests
- `extensions/Others/Cancellations/Support/Requests.php` — accept / refuse
- `extensions/Others/Cancellations/Admin/Resources/CancellationRequestResource.php` — the list with both actions
- `extensions/Others/Cancellations/README.md`

## Invoice operations
- `extensions/Others/InvoiceOps/InvoiceOps.php` — draft status, refunds, manual notices
- `extensions/Others/InvoiceOps/Support/Drafts.php` — the draft scope and Publish
- `extensions/Others/InvoiceOps/Support/Refunds.php` — recorded refunds and Reverse Payment
- `extensions/Others/InvoiceOps/Models/InvoiceRefund.php`
- `extensions/Others/InvoiceOps/Admin/Resources/InvoiceOpsResource.php` — Billing → Invoice Operations
- `extensions/Others/InvoiceOps/database/migrations/` — `ext_invoice_refunds`
- `docs/modules/invoice-ops.md`

## Billable items
- `extensions/Others/BillableItems/BillableItems.php` — rides along on new invoices, daily sweep
- `extensions/Others/BillableItems/Support/Items.php` — invoicing, recurrence, the sweep
- `extensions/Others/BillableItems/Models/BillableItem.php`
- `extensions/Others/BillableItems/Admin/Resources/BillableItemResource.php` — Billing → Billable Items
- `extensions/Others/BillableItems/database/migrations/` — `ext_billable_items`
- `docs/modules/billable-items.md`

## Deployment / operations
- `scripts/install-debian13.sh` — automated Debian 13 installer
- `scripts/backup.sh` — DB + files backup
- `scripts/restore.sh` — restore from backup

## Documentation
- `README.md` — project overview & status matrix
- `docs/00-project-plan.md` — scope, spec→implementation mapping, phases
- `docs/01-installation.md` — Debian 13 install, HTTPS, queues, backups
- `docs/02-updates.md` — upgrade-safe update procedure
- `docs/12-security.md` — security practices & checklist
- `docs/modules/coinpayments.md`
- `docs/modules/proxypanel.md`
- `docs/AUTHORED-FILES.md` — this file
- `CLAUDE.md` — repo guidance for contributors/AI assistants

## Config
- `.gitignore` — tailored to commit custom themes/extensions

## Planned (not yet authored — see `docs/00-project-plan.md`)
- `extensions/Gateways/Binance/` — Binance Pay gateway
- `extensions/Others/PaymentFees/` — payment method fees
- `extensions/Others/GatewayRules/` — country/product/currency gateway availability
- `extensions/Others/Notifications/` — Email + Telegram notifications
- `themes/proxy/` — custom client-area theme
- `docs/10-disable-domains.md`, `docs/CORE-TOUCHPOINTS.md`
