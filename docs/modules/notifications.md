# Notification System (Email + Telegram)

Spec item 11: a centralized notification system over **Email** and **Telegram**, for
**customers and administrators**, covering payments, tickets, service provisioning,
suspension, cancellation, critical failures, webhooks and administrative changes —
**queued with automatic retry**.

## Architecture — extend, don't duplicate

Paymenter core **already ships a full notification system**:

- `App\Helpers\NotificationHelper` dispatches **email** (queued, via
  `NotificationTemplate`s that admins edit in the panel) and **in-app** notifications
  for every event, honouring per-user preferences (`NotificationPreference`).
- Events, templates, subscriptions, a client-area Notifications page and push are all
  built in.

What core does **not** have is **Telegram**. So this project adds a Telegram delivery
layer and admin/critical alerting as the `Others/Notifications` extension, **without
editing core** — it plugs into the existing event flow.

| Channel | Provided by | Events |
|---|---|---|
| **Email** | Paymenter core (`NotificationTemplate` + queued Mail) | all |
| **In-app / push** | Paymenter core (`Notification` model) | all |
| **Telegram (customer)** | this extension — mirrors each in-app notification | all core events |
| **Telegram (admin/critical)** | this extension — new tickets, cancellations, failures | operational |

Because the customer Telegram layer listens to `App\Events\Notification\Created`
(fired whenever core creates an in-app notification), it automatically covers every
event core already notifies on — payments, invoices, tickets, provisioning,
suspension, cancellation — with no per-event wiring.

## Files

- `extensions/Others/Notifications/Notifications.php` — extension: config, event
  listeners, `notifyAdmins()` / `notifyCritical()` helpers.
- `extensions/Others/Notifications/Jobs/SendTelegramMessage.php` — queued Telegram
  delivery (5 retries, backoff 10s→15m).
- `extensions/Others/Notifications/database/migrations/*_seed_telegram_chat_property.php`
  — adds an optional **Telegram Chat ID** field to customer profiles.

## Setup

### 1. Email (core)
Configure SMTP in **Admin → Settings → Mail**, then edit templates under
**Admin → Notifications** (subject/body/enabled, per-channel). Nothing to install.

### 2. Telegram (this extension)
1. Create a bot with [@BotFather](https://t.me/BotFather) and copy the **bot token**.
2. Enable **Notifications** under **Admin → Extensions**, then set:
   - **Telegram Bot Token** (stored encrypted),
   - **Admin Chat ID** — the chat/group that receives admin & critical alerts,
   - the per-audience toggles (customers / tickets / cancellations).
3. **Customers**: each customer opens their **Account → Personal Details** and fills in
   **Telegram Chat ID** (they get theirs by messaging the bot and visiting
   `https://api.telegram.org/bot<token>/getUpdates`, or via a chat-id bot).

### Finding a chat id
- Personal: message your bot, then open
  `https://api.telegram.org/bot<TOKEN>/getUpdates` and read `message.chat.id`.
- Group: add the bot to the group and do the same; group ids are negative.

## Queue & retry

Delivery runs on the Laravel queue (`SendTelegramMessage`), so the queue worker must be
running (`php artisan queue:work`, or the `paymenter-queue` systemd unit from
`scripts/install-debian13.sh`). Failed sends retry 5× with backoff; a final failure is
logged (`[Notifications/Telegram]`) and never blocks the request.

## Admin & critical alerts from other modules

Any module can push an admin/critical alert without knowing about Telegram:

```php
app(\Paymenter\Extensions\Others\Notifications\Notifications::class)
    ->notifyCritical('ProxyPanel: provisioning failed for service #123');
```

This is the intended hook for **critical failures** and **webhook errors** (spec item 11).
Gateways and the ProxyPanel module can call it in their `catch` blocks.

## Security

- Bot token stored as an **encrypted** extension setting; never hard-coded.
- Messages are HTML-escaped before sending; only ids/labels are included, no secrets.
- Admin alerts go only to the configured admin chat id.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| No Telegram messages | Queue worker not running; or bot token / chat id empty |
| Customer gets nothing | They haven't saved a **Telegram Chat ID**, or "notify customers" is off |
| "chat not found" in logs | Wrong chat id, or the user/group never messaged the bot first |
| Admin alerts missing | **Admin Chat ID** empty, or the specific toggle disabled |


## Events (scope §11)

All eight of the scope's minimum events reach the admin channel, each behind its own
toggle in the extension settings:

| Scope event | Source | Setting |
|---|---|---|
| Payments | `Invoice\Paid` | `notify_admin_payments` |
| Support tickets | `Ticket\Created` + `TicketMessage\Created` (customer replies only) | `notify_admin_tickets` |
| Service provisioning | `Service\Updated` → active | `notify_admin_provisioning` |
| Service suspension | `Service\Updated` → suspended | `notify_admin_suspensions` |
| Service cancellation | `ServiceCancellation\Created` | `notify_admin_cancellations` |
| Critical failures | `ProvisioningOps::failed()` → `provisioningFailed()` | `notify_admin_provisioning` |
| Webhooks | `InvoiceTransaction\Created` — succeeded/failed only | `notify_admin_webhooks` |
| Administrative changes | `User\Created` | `notify_admin_changes` |

Customers additionally receive every in-app notification mirrored to Telegram
(`notify_customers`), once they link a chat id.

Only terminal gateway states are announced — a webhook that merely moves a transaction to
*processing* is not worth waking anyone for, and CoinPayments sends several per payment.
Failed gateway transactions and provisioning failures go out as **critical** alerts.

### Delivery never breaks the operation

`SendTelegramMessage` throws on failure so the queue worker retries it with backoff. That
is correct for a queued job, but on a `sync` queue the exception would propagate into
whatever triggered it — a Telegram outage could fail a provisioning run or a payment.
Dispatch is therefore wrapped: a delivery problem is logged and dropped, never raised.

This was found by running the provisioning suite with Telegram configured but
unreachable — every provisioning test failed until the dispatch was contained.
