<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's email-template editor (issue #48, "not 100% implemented"): the
 * settings band — Template Name, Subject, Copy To, Blind Copy To, Disable — over the
 * body with a Source / Preview toggle.
 *
 * Deliberately NOT a WYSIWYG: these bodies are Markdown carrying live Blade
 * placeholders ({{ $ip }}, {{ route(...) }}), and a rich-text editor rewrites markup it
 * does not understand — one save would corrupt every placeholder in the template. The
 * reference itself ships a Source code view for exactly this reason; here that view is
 * the editor, and Preview shows the rendered Markdown with the placeholders highlighted
 * rather than executed (running arbitrary Blade from an editable field would be an RCE).
 */
class EditEmailTemplate extends Page
{
    protected string $view = 'adminops::pages.edit-email-template';

    protected static ?string $slug = 'edit-email-template';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Same reasoning as {@see ClientSummary::$customer} — not `$record`. */
    public NotificationTemplate $template;

    public string $subject = '';

    public string $cc = '';

    public string $bcc = '';

    public bool $disabled = false;

    public string $body = '';

    /** source | preview — the reference's rich-text/source toggle, honest version. */
    public string $mode = 'source';

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public static function canAccess(): bool
    {
        return NotificationTemplateResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Email Templates';
    }

    public function mount(int|string $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->template = NotificationTemplate::findOrFail($record);
        $this->subject = (string) $this->template->subject;
        $this->cc = implode(', ', (array) $this->template->cc);
        $this->bcc = implode(', ', (array) $this->template->bcc);
        $this->disabled = !$this->template->enabled;
        $this->body = (string) $this->template->body;
    }

    public function save(): void
    {
        $this->validate([
            'subject' => 'required|string|max:255',
            'cc' => 'nullable|string|max:1000',
            'bcc' => 'nullable|string|max:1000',
            'body' => 'required|string',
        ], attributes: ['cc' => 'copy to', 'bcc' => 'blind copy to']);

        $split = fn (string $list): array => array_values(array_filter(array_map('trim', explode(',', $list))));

        foreach (['cc', 'bcc'] as $field) {
            foreach ($split($this->{$field}) as $address) {
                if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, '"' . $address . '" is not a valid email address.');

                    return;
                }
            }
        }

        // No `name` in the update: the model lists one in $fillable but the table has no
        // such column (caught live — the save 500'd on "Unknown column 'name'"). The
        // display name derives from the key, same as the list page's labels.
        $this->template->update([
            'subject' => $this->subject,
            'cc' => $split($this->cc),
            'bcc' => $split($this->bcc),
            'enabled' => !$this->disabled,
            'body' => $this->body,
        ]);

        Notification::make()->title('Template saved')->success()->send();
    }

    /**
     * The Preview pane: Markdown rendered to HTML with every Blade placeholder shown as
     * a highlighted token instead of being executed. `{{ $ip }}` reads as a chip named
     * `$ip`; nothing typed into the body ever runs.
     */
    public function previewHtml(): string
    {
        $tokenised = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<code class="ao-ete-token">{{ $1 }}</code>',
            e($this->body),
        );

        try {
            return Str::markdown($tokenised, ['html_input' => 'allow']);
        } catch (\Throwable $e) {
            return '<p>' . e($e->getMessage()) . '</p>';
        }
    }
}
