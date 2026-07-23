# Notifications (Telegram layer)

Adds **Telegram** delivery and admin/critical alerting on top of Paymenter's built-in
email + in-app notification system — queued, with automatic retry, for customers and
admins. No core edits.

Full documentation: [`docs/modules/notifications.md`](../../../docs/modules/notifications.md).

**Enable:** Admin → Extensions → Notifications, then set the Bot Token, Admin Chat ID
and toggles. Customers add their **Telegram Chat ID** in Account → Personal Details.

> Requires the queue worker to be running (`php artisan queue:work`).
