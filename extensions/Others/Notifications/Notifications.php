<?php

namespace Paymenter\Extensions\Others\Notifications;

use App\Classes\Extension\Extension;
use App\Events\Notification\Created as NotificationCreated;
use App\Events\ServiceCancellation\Created as CancellationCreated;
use App\Events\Ticket\Created as TicketCreated;
use App\Helpers\ExtensionHelper;
use App\Models\Notification as NotificationModel;
use App\Models\ServiceCancellation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\Notifications\Jobs\SendTelegramMessage;

/**
 * Centralised notification system — Telegram delivery layer.
 *
 * Paymenter core already delivers **email** and **in-app** notifications for every
 * supported event (payments, tickets, provisioning, suspension, cancellation, …)
 * through App\Helpers\NotificationHelper + admin-editable NotificationTemplates,
 * with per-user preferences. This extension adds the missing **Telegram** channel
 * and admin/critical alerting, WITHOUT modifying core:
 *
 *  - Customer Telegram: every in-app notification core creates is mirrored to the
 *    customer's Telegram (if they saved their chat id), via the Notification\Created
 *    event. This automatically covers all events core notifies on.
 *  - Admin Telegram: key operational + critical events are pushed to a configured
 *    admin chat (new tickets, cancellations, and — via notifyAdmins()/notifyCritical()
 *    helpers other modules can call — provisioning failures, webhook errors, etc.).
 *  - All delivery is queued with automatic retry (see SendTelegramMessage).
 *
 * Credentials are encrypted extension settings; nothing is hard-coded.
 *
 * @link docs/modules/notifications.md
 */
class Notifications extends Extension
{
    /** User property where a customer stores their Telegram chat id. */
    public const USER_CHAT_KEY = 'telegram_chat_id';

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'bot_token',
                'label' => 'Telegram Bot Token',
                'type' => 'text',
                'description' => 'Token from @BotFather. Stored encrypted. Used for all Telegram delivery.',
                'required' => false,
                'encrypted' => true,
            ],
            [
                'name' => 'admin_chat_id',
                'label' => 'Admin Chat ID',
                'type' => 'text',
                'description' => 'Telegram chat/group id that receives admin & critical alerts (new tickets, cancellations, failures). Leave empty to disable admin alerts.',
                'required' => false,
            ],
            [
                'name' => 'notify_customers',
                'label' => 'Send customer notifications to Telegram',
                'type' => 'checkbox',
                'description' => 'Mirror each customer in-app notification to their Telegram (if they saved a chat id in their profile).',
                'required' => false,
            ],
            [
                'name' => 'notify_admin_tickets',
                'label' => 'Alert admins on new tickets',
                'type' => 'checkbox',
                'required' => false,
            ],
            [
                'name' => 'notify_admin_cancellations',
                'label' => 'Alert admins on service cancellations',
                'type' => 'checkbox',
                'required' => false,
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/Notifications/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/Notifications/database/migrations');
    }

    public function boot()
    {
        // ── Customer channel: mirror every in-app notification to Telegram ──────
        Event::listen(NotificationCreated::class, function (NotificationCreated $event) {
            if (!$this->config('notify_customers')) {
                return;
            }
            $this->mirrorToCustomer($event->notification);
        });

        // ── Admin channel: new support tickets ──────────────────────────────────
        Event::listen(TicketCreated::class, function (TicketCreated $event) {
            if (!$this->config('notify_admin_tickets')) {
                return;
            }
            $this->notifyAdmins($this->formatTicket($event->ticket));
        });

        // ── Admin channel: service cancellations ────────────────────────────────
        Event::listen(CancellationCreated::class, function (CancellationCreated $event) {
            if (!$this->config('notify_admin_cancellations')) {
                return;
            }
            $this->notifyAdmins($this->formatCancellation($event->cancellation));
        });
    }

    // ── Public helpers other modules can call for critical/admin alerts ─────────

    /**
     * Send a message to the configured admin chat. Safe no-op if unconfigured.
     * Other modules (gateways, ProxyPanel) can call this for failures/webhooks.
     */
    public function notifyAdmins(string $message): void
    {
        $token = (string) $this->config('bot_token');
        $chat = (string) $this->config('admin_chat_id');
        if ($token === '' || $chat === '') {
            return;
        }
        SendTelegramMessage::dispatch($token, $chat, $message);
    }

    /** Convenience for critical failures (prefixed + always attempted). */
    public function notifyCritical(string $message): void
    {
        $this->notifyAdmins("\u{1F6A8} <b>Critical</b>\n" . $message);
        Log::channel('stack')->critical('[Notifications] ' . strip_tags($message));
    }

    // ── Internal ────────────────────────────────────────────────────────────────

    private function mirrorToCustomer(NotificationModel $notification): void
    {
        $token = (string) $this->config('bot_token');
        if ($token === '' || !$notification->user_id) {
            return;
        }

        $chatId = optional(
            User::find($notification->user_id)
        )?->properties()->where('key', self::USER_CHAT_KEY)->value('value');

        if (!$chatId) {
            return;
        }

        $text = '<b>' . e($notification->title) . '</b>';
        if ($notification->body) {
            $text .= "\n" . e(strip_tags($notification->body));
        }
        if ($notification->url) {
            $text .= "\n" . e($notification->url);
        }

        SendTelegramMessage::dispatch($token, (string) $chatId, $text);
    }

    private function formatTicket(Ticket $ticket): string
    {
        return "\u{1F3AB} <b>New ticket</b> #{$ticket->id}\n"
            . e($ticket->subject)
            . ($ticket->user ? "\nFrom: " . e($ticket->user->email) : '');
    }

    private function formatCancellation(ServiceCancellation $cancellation): string
    {
        $service = $cancellation->service;

        return "\u{26D4} <b>Service cancellation requested</b>\n"
            . 'Service: ' . e($service->label ?? ('#' . $service->id))
            . ($service->user ? "\nCustomer: " . e($service->user->email) : '');
    }
}
