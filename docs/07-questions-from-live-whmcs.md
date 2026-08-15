# Questions for Leandro

Most of the earlier list turned out to be answerable from the code or from Paymenter's own
settings, and has been removed or implemented. What is left is genuinely open.

---

## A. Blocked — needs action, not an answer

| # | Blocker | Effect |
|---|---|---|
| A1 | **Panel has no locations.** `GET /locations` → `[]`; orders rejected with *"Requested location not available"* | **No proxy can be delivered.** Everything else is ready |
| A2 | **SMTP credentials** (`mail.host` is still `127.0.0.1`) | No customer email of any kind |
| A3 | **Telegram** bot token + admin chat id | No Telegram alerts |
| A4 | **Cryptomus** — API not activated in their dashboard | Configured, cannot charge |
| A5 | **Binance** — HTTP 451 from the US-hosted server | Needs a hosting/region decision |

## B. Genuinely open questions

| # | Question | Why it cannot be guessed |
|---|---|---|
| B1 | **What is painful in WHMCS today that this project must remove?** | Reframes priorities. No amount of code inspection answers it |
| B2 | **Are tickets created and answered by email?** | Mail ingestion is a substantial feature. The `ticket_mail_log_id` column exists but nothing ingests. Building it speculatively is wasted work if they only use the web form |
| B3 | **Does anything depend on ticket priority** — SLA, escalation, alerting? | Priority is stored and sortable. Any behaviour beyond that would be invented |

## C. Data to supply — no development needed

| # | Item | Current state |
|---|---|---|
| C1 | **Real prices** for the 9 plans, USD and BRL | Placeholders, tier-derived at R$5.40/USD |
| C2 | **Gateway rules** — which method for which country/product/amount | Engine supports all six dimensions; no rules configured |
| C3 | **Fee per payment method** | Module active; no fees configured |
| C4 | **Current notification templates**, if their wording should be kept | 12 templates present, wording is ours |
| C5 | **Department list**, and who each routes to | Routing now supported — see below |

---

## Already answered — no longer questions

Checked against the code rather than asked:

| Was asked | Answer |
|---|---|
| Ticket auto-close after inactivity | Core setting, **7 days** |
| Renewal grace before suspension | Core setting, **suspend at 2 days overdue**, terminate at 14, remind at 3 |
| Quick replies global or per-department | Blank department already means "any department" |
| Backup retention | `backup.sh` already prunes by `RETENTION_DAYS` |
| Who may read a stored CPF/CNPJ | Encrypted at rest; gated by `admin.brazilian.view_documents` |
| Is CPF mandatory at signup | Per-field `required` flag, set in Admin → Custom Properties |
| What the customer sees before delivery | Service held **Pending** until the panel confirms |

## Implemented instead of asked

**Department routing.** WHMCS assigns by department; Paymenter stored the department but
left assignment manual. TicketTools now takes a plain-text routing table in its settings:

```
Technical Support = support@example.com
Billing           = billing@example.com
```

A new ticket in a matching department is assigned to that admin automatically. An unmatched
department stays unassigned rather than guessing, and a routing failure never blocks the
ticket being raised. Verified on the server: a "Technical Support" ticket routed to the
configured admin, and a "Billing" ticket stayed unassigned.

Only **C5** remains — the real department list and who each should route to.
