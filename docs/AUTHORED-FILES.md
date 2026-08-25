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
- `extensions/Others/AdminOps/AdminOps.php` — widget styles, sidebar service queues
- `extensions/Others/AdminOps/Admin/Widgets/Shortcuts.php` — dashboard quick actions
- `extensions/Others/AdminOps/Admin/Widgets/AtAGlance.php` — income/services/customers/tickets by period
- `extensions/Others/AdminOps/Admin/Widgets/ActionQueue.php` — "Needs attention" work queue
- `extensions/Others/AdminOps/Admin/Pages/ClientSummary.php` — one-screen customer view + log in as customer
- `extensions/Others/AdminOps/Support/Metrics.php` — every dashboard figure, defined once
- `extensions/Others/AdminOps/Support/Money.php` — multi-currency-safe money formatting
- `extensions/Others/AdminOps/Support/Links.php` — cross-extension admin URLs
- `extensions/Others/AdminOps/resources/views/` — widget/page templates and their stylesheet
- `extensions/Others/AdminOps/README.md`
- `docs/02b-admin-area.md`

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
