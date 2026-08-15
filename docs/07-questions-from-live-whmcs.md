# Questions for Leandro

The PDF specifies features. A running WHMCS also encodes behaviour — routing, defaults,
wording, who sees what. These are the points where we had to choose, and where a wrong
choice means rework.

**No production logins needed.** Screenshots, a short screen recording, or a read-only
staging copy answers all of it without exposing live customer data.

---

## A. Blocked — needs action, not an answer

| # | Blocker | Effect |
|---|---|---|
| A1 | **Panel has no locations.** `GET /locations` → `[]`; orders rejected with *"Requested location not available"* | **No proxy can be delivered.** Everything else is ready |
| A2 | **SMTP credentials** missing (`mail.host` = `127.0.0.1`) | No customer email of any kind |
| A3 | **Telegram** bot token + admin chat id missing | No Telegram alerts |
| A4 | **Cryptomus**: API not activated in dashboard | Gateway configured, cannot charge |
| A5 | **Binance**: HTTP 451 from US-hosted server | Needs a region/hosting decision |

---

## B. Answer these first — they change what gets built

| # | Question | Built today |
|---|---|---|
| B1 | **What is painful in WHMCS today that this project must remove?** | — |
| B2 | Are tickets **created/replied by email**? | Web only. Column exists, no ingestion |
| B3 | Do tickets **auto-assign by department**? Which departments, to whom? | Department stored; assignment manual |
| B4 | **Renewal policy** — auto-charge, invoice-then-suspend, grace period? | Billing-driven, no grace rule |
| B5 | **Which gateway is offered to whom** today (country, product, amount)? | Engine supports all 6 rules; none configured |
| B6 | **What fee** does each method carry, and is it shown before choosing? | Added to invoice at selection |
| B7 | Is **CPF/CNPJ mandatory** at signup? Who may read a stored document? | Optional; any admin can read |

---

## C. Answer before launch

| # | Question | Built today |
|---|---|---|
| C1 | **Real prices** for the 9 plans, in USD and BRL | Placeholders (tier-derived, R$5.40/USD) |
| C2 | Is **BRL** the selling currency, or display only? | Added alongside USD |
| C3 | **Which events notify whom** today? Send current email templates | 8 events wired; wording is ours |
| C4 | Do tickets **auto-close** after inactivity? After how long? | No auto-close |
| C5 | Does **priority** drive anything (SLA, sorting, alerts)? | Stored, no behaviour |
| C6 | Are **quick replies** global or per-department? | Per-department |
| C7 | What does a customer **see between ordering and delivery**? | Service held *Pending* until panel confirms |
| C8 | **Backup retention** and recovery expectation? | Scripts shipped, no policy set |

---

## D. Fastest way to answer most of this

1. **Screen recording of one normal day** — an order arriving through to the service going
   live, and one ticket handled start to finish. Covers B2, B3, B4, C3, C7.
2. **Export the current notification templates.** Covers C3.
3. **Screenshot the gateway and fee rules** in force. Covers B5, B6.
