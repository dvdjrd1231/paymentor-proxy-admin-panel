<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #48 — WHMCS's Email Templates screen: templates grouped into the reference's
 * message categories, each section a navy mini-grid of Status, Template Name and the
 * edit icon, laid out in the reference's two columns. Every row is one of Paymenter's
 * real notification templates; editing stays on core's form, which owns the body,
 * subject, CC/BCC and channel switches.
 */
class EmailTemplates extends Page
{
    protected string $view = 'adminops::pages.email-templates';

    protected static ?string $slug = 'email-templates';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The reference's sections, with the real template keys that belong in each. */
    private const SECTIONS = [
        'General Messages' => ['new_order_created', 'service_cancellation_received'],
        'User Messages' => ['email_verification', 'password_reset', 'new_login_detected'],
        'Invoice Messages' => ['new_invoice_created', 'invoice_paid', 'invoice_payment_failed'],
        'Product/Service Messages' => ['new_server_created', 'server_suspended', 'server_terminated'],
        'Support Messages' => ['new_ticket_message'],
    ];

    public static function canAccess(): bool
    {
        return NotificationTemplateResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Email Templates';
    }

    public function getSubheading(): ?string
    {
        return 'The email templates that come supplied with the system are sent when certain '
            . 'events occur. Editing one changes its subject, body and delivery channels.';
    }

    protected function getViewData(): array
    {
        $templates = NotificationTemplate::orderBy('key')->get()->keyBy('key');
        $filed = collect(self::SECTIONS)->flatten();

        $sections = collect(self::SECTIONS)
            ->map(fn (array $keys) => collect($keys)
                ->map(fn (string $key) => $templates[$key] ?? null)
                ->filter()
                ->values())
            ->filter(fn ($rows) => $rows->isNotEmpty());

        // A template added by an update must appear rather than silently vanish.
        $unfiled = $templates->reject(fn ($template) => $filed->contains($template->key))->values();

        if ($unfiled->isNotEmpty()) {
            $sections['Other Messages'] = $unfiled;
        }

        return [
            'sections' => $sections,
            // Issue #48: the WHMCS-shaped editor, not core's raw resource form.
            'edit' => fn (NotificationTemplate $template) => NotificationTemplateResource::canEdit($template)
                ? EditEmailTemplate::getUrl(['record' => $template->id])
                : null,
            'newUrl' => NotificationTemplateResource::canCreate()
                ? NotificationTemplateResource::getUrl('create')
                : null,
        ];
    }

    /** "invoice_paid" reads as "Invoice Paid" — the row label, from the real key. */
    public static function label(NotificationTemplate $template): string
    {
        return str($template->key)->replace('_', ' ')->title();
    }
}
