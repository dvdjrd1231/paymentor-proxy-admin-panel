# What we still need to see from the live WHMCS

Leandro is right that parts of the specification only resolve against a running system. The
PDF describes *features*; a production install also encodes *behaviour* — routing rules,
defaults, wording, who sees what — and that is where an implementation can be complete
against the document and still feel wrong to the people using it.

This is the concrete list. Each item says what was built, what is ambiguous, and the
smallest thing that would settle it.

> **Access:** we are not asking for logins to the production billing system. Screenshots or
> a short screen recording of each flow, or a read-only copy restored to a staging host, is
> enough — and avoids putting live customer data at risk.

---

## §3 Support tickets

| Question | Why it matters | Built today |
|---|---|---|
| What are the actual **departments**, and does each route to different staff or a different mailbox? | We store a department per ticket, but not routing. If tickets are auto-assigned by department, that is logic we have not written. | Free-text department on the ticket; assignment is manual (`assigned_to`) |
| Are tickets **created by email** (mail piping)? | WHMCS commonly ingests replies from an inbox. If customers reply by email today, they will expect that to keep working. | Web only. There is a `ticket_mail_log_id` column but no ingestion |
| Do tickets **auto-close** after inactivity, and after how long? | Changes reporting and customer expectation. | No auto-close |
| Which **priorities** do staff actually use, and does priority drive anything (SLA, sorting, alerts)? | We store priority but nothing acts on it. | `priority` stored, no behaviour attached |
| Are **quick replies** shared by all staff or per-department? | We key canned responses by department; if they are global that field is noise. | `canned_responses.department` |

## §2 Administrative area — "optimized for daily operations"

This is the least specifiable line in the PDF and the easiest to get wrong.

- **Which screens does staff live in all day?** Orders queue, ticket queue, a specific
  customer view? We have built an operations metrics widget, but not around a workflow we
  have watched.
- **What does a normal day look like** — new order arrives, then what? Which steps are
  manual today and which are expected to become automatic?
- **What is currently painful in WHMCS** that this project is meant to remove? That single
  answer is worth more than the rest of this document.

## §5 / §6 Gateway rules and fees

- **Which gateways are offered to whom today?** We support rules by country, product,
  product group, customer type, currency and amount — but we do not know the rules they
  actually run.
- **What fee does each method carry today**, and is it shown to the customer before they
  choose? Our fee is added to the invoice at selection time.

## §7 / §8 Provisioning

- **What does the customer see between ordering and the proxies being ready?** We hold the
  service Pending until the panel confirms; if WHMCS shows something else, expectations differ.
- **How are renewals handled today** — automatic charge, invoice-then-suspend, grace period?
  Paymenter has no renew hook, so renewal is billing-driven, and the grace behaviour is a
  policy decision we have not been given.
- **What happens on failure today?** Who is told, and how quickly?

## §9 Brazilian registration

- Is **CPF/CNPJ mandatory at signup**, or can an account exist without one?
- Who may **see a stored document** — any admin, or a restricted role?
- Is the document ever shown back to the customer, masked or in full?

## §11 Notifications

- **Which events actually notify today, and who receives each?** The PDF lists eight
  minimum events; a live system usually has a longer list and specific recipients.
- **What do the current emails look like?** Wording and branding are part of "similar to
  WHMCS", and copying the existing templates is cheaper than inventing them.

## §1 Deployment

- **What is the current backup and restore routine**, and what recovery expectation exists?
  We ship scripts; we do not know the retention or the RTO they assume.

---

## The three that block delivery regardless

These are not questions — they are outstanding actions on the client side, and no amount of
code moves them:

1. **Panel has no locations.** `GET /locations` returns `[]` and orders are rejected with
   *"Requested location not available"*. Until a location exists, no proxy can be delivered.
2. **SMTP and Telegram credentials.** The notification system is built and wired to all
   eight events, but nothing can send.
3. **Cryptomus API activation** and a **Binance hosting-region decision** (HTTP 451).

---

## Suggested order

1. A **screen recording of one normal day** in the current WHMCS — an order arriving through
   to the service being live, and one ticket handled start to finish. This answers most of
   the questions above at once.
2. The **current notification templates**, exported.
3. The **gateway and fee rules** in force today.

Everything else can follow from those three.
