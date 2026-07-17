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
- `extensions/Servers/ProxyPanel/README.md`

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
- `extensions/Others/BrazilianRegistration/` — CPF/CNPJ registration
- `themes/proxy/` — custom client-area theme
- `docs/10-disable-domains.md`, `docs/CORE-TOUCHPOINTS.md`
