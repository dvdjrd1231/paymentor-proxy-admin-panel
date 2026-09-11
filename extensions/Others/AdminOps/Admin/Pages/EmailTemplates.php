<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Paymenter\Extensions\Others\AdminOps\Models\TemplateLocale;
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

    /** null | 'create' | 'languages' — the reference opens both from the button strip. */
    public ?string $modal = null;

    public string $newType = 'General Messages';

    public string $newName = '';

    public static function canAccess(): bool
    {
        return NotificationTemplateResource::canViewAny();
    }

    public function openModal(string $which): void
    {
        $this->resetValidation();
        $this->modal = $which;

        // The reference opens with the first language already chosen rather than on a
        // blank, so Activate is live the moment the dialog appears.
        if ($which === 'languages') {
            $this->newLocale = (string) array_key_first($this->availableLocales());
        }
    }

    /** The reference's Create New Email Template dialog: a type and a unique name. */
    public function createTemplate(): void
    {
        abort_unless(NotificationTemplateResource::canCreate(), 403);

        $this->validate([
            'newName' => 'required|string|max:255',
            'newType' => 'required|in:' . implode(',', array_keys(self::SECTIONS)),
        ], attributes: ['newName' => 'unique name', 'newType' => 'email type']);

        $key = (string) str($this->newName)->snake();

        if ($key === '' || NotificationTemplate::where('key', $key)->exists()) {
            $this->addError('newName', 'A template named "' . $this->newName . '" already exists.');

            return;
        }

        $template = NotificationTemplate::create([
            'key' => $key,
            'subject' => $this->newName,
            'body' => '# ' . $this->newName . "\n\nWrite the message here.",
            'enabled' => true,
        ]);

        \Paymenter\Extensions\Others\AdminOps\Models\Meta::put($template, 'section', $this->newType);

        $this->reset(['modal', 'newName']);

        $this->redirect(EditEmailTemplate::getUrl(['record' => $template->id]));
    }

    /** The reference's Manage Languages: the language picked in the dialog's select. */
    public string $newLocale = '';

    /**
     * Activate a language, so every template can carry a translation of its subject and
     * body and clients reading that language are sent it.
     */
    public function activateLocale(): void
    {
        Gate::authorize('has-permission', 'admin.settings.update');

        $available = $this->availableLocales();

        if ($this->newLocale === '' || ! array_key_exists($this->newLocale, $available)) {
            $this->addError('newLocale', 'Choose a language to activate.');

            return;
        }

        // Read the name before the reset: `newLocale` is blank afterwards, and looking it
        // up then is an undefined key rather than a language.
        $label = $available[$this->newLocale];

        TemplateLocale::setActive([...TemplateLocale::active(), $this->newLocale]);

        $this->reset(['modal', 'newLocale']);

        Notification::make()
            ->title($label . ' activated')
            ->body('Each template now has a version in this language, under its default one.')
            ->success()->send();
    }

    /**
     * Deactivate a language. The translations themselves are kept: switching a language
     * off should not throw away the wording someone wrote, and turning it back on is then
     * a click rather than a retype. Nothing sends them while it is off.
     */
    public function deactivateLocale(string $locale): void
    {
        Gate::authorize('has-permission', 'admin.settings.update');

        TemplateLocale::setActive(array_filter(TemplateLocale::active(), fn (string $l): bool => $l !== $locale));

        Notification::make()
            ->title(($this->allLocales()[$locale] ?? $locale) . ' deactivated')
            ->body('Its translations are kept, and nothing sends them until it is activated again.')
            ->success()->send();
    }

    /** Every installed language, code => readable name. */
    private function allLocales(): array
    {
        return AddNewClient::languages();
    }

    /**
     * The languages that can still be added: the installed ones, less those already
     * active, less the default. English is the default version's own language, so
     * offering it would create a translation that could never be chosen over it.
     */
    private function availableLocales(): array
    {
        // The reference lists English too — activating the store's own default is a
        // harmless no-op for sending, and leaving it out read as a missing language.
        return array_diff_key($this->allLocales(), array_flip(TemplateLocale::active()));
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

        // A template added by an update, or created from the dialog, must appear rather
        // than silently vanish. One created here remembers which section it chose.
        $unfiled = $templates->reject(fn ($template) => $filed->contains($template->key))->values();

        $meta = \Paymenter\Extensions\Others\AdminOps\Models\Meta::forMany(NotificationTemplate::class, $unfiled);

        foreach ($unfiled as $template) {
            $section = $meta[$template->id]['section'] ?? 'Other Messages';
            $sections[$section] = ($sections[$section] ?? collect())->push($template);
        }

        $all = $this->allLocales();
        $active = TemplateLocale::active();

        return [
            'sections' => $sections->filter(fn ($rows) => $rows->isNotEmpty()),
            // The reference's Manage Languages dialog: what is on, and what can be added.
            'activeLocales' => array_combine($active, array_map(fn (string $l): string => $all[$l] ?? strtoupper($l), $active)),
            'addableLocales' => $this->availableLocales(),
            // Issue #48: the WHMCS-shaped editor, not core's raw resource form.
            'edit' => fn (NotificationTemplate $template) => NotificationTemplateResource::canEdit($template)
                ? EditEmailTemplate::getUrl(['record' => $template->id])
                : null,
            'canCreate' => NotificationTemplateResource::canCreate(),
            'types' => array_keys(self::SECTIONS),
        ];
    }

    /** "invoice_paid" reads as "Invoice Paid" — the row label, from the real key. */
    public static function label(NotificationTemplate $template): string
    {
        return str($template->key)->replace('_', ' ')->title();
    }
}
