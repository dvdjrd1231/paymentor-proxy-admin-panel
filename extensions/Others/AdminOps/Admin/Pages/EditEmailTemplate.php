<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Paymenter\Extensions\Others\AdminOps\Models\EmailTemplateAttachment;
use Paymenter\Extensions\Others\AdminOps\Models\TemplateLocale;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's email-template editor (issue #48, "not 100% implemented"): the
 * settings band — Template Name, Subject, Copy To, Blind Copy To, Disable — over the
 * body with a Source / Preview toggle.
 */
class EditEmailTemplate extends Page
{
    use WithFileUploads;

    /**
     * Files queued by the reference's Attachments row — one per Choose File, as its
     * Add More adds rows.
     *
     * @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null>
     */
    public array $attachments = [null];

    public ?int $removingAttachment = null;

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

    /**
     * The reference's per-language versions, [locale => ['subject' => …, 'body' => …]].
     * One entry per language Manage Languages has activated.
     *
     * @var array<string, array{subject: string, body: string}>
     */
    public array $locales = [];

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

    /** The reference names the template under the page's own title, not in a form row. */
    public function getSubheading(): ?string
    {
        return EmailTemplates::label($this->template);
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

        // A version per language Manage Languages has switched on. Blank until someone
        // writes one, and a blank one is not sent — see TemplateLocale::resolve().
        $stored = TemplateLocale::where('notification_template_id', $this->template->id)->get()->keyBy('locale');

        foreach (TemplateLocale::active() as $locale) {
            $this->locales[$locale] = [
                'subject' => (string) ($stored[$locale]->subject ?? ''),
                'body' => (string) ($stored[$locale]->body ?? ''),
            ];
        }
    }

    public function addAttachmentRow(): void
    {
        $this->attachments[] = null;
    }

    /**
     * Store what the Attachments rows hold against this template.
     *
     * @return int how many files were taken
     */
    private function storeQueuedAttachments(): int
    {
        abort_unless(NotificationTemplateResource::canEdit($this->template), 403);

        $this->validate([
            'attachments.*' => 'nullable|file|max:10240',
        ], ['attachments.*.max' => 'Each attachment must be 10 MB or smaller.']);

        $stored = 0;

        foreach (array_filter($this->attachments) as $upload) {
            // Facts before the move: storeAs() takes the upload out of livewire-tmp, and
            // reading its size afterwards throws on a path that is already gone.
            $filename = $upload->getClientOriginalName();
            $filesize = $upload->getSize();
            $mime = (string) $upload->getMimeType();
            $name = Str::ulid() . '.' . ($upload->getClientOriginalExtension() ?: 'bin');

            $upload->storeAs('email-templates/attachments', $name);

            EmailTemplateAttachment::create([
                'template_id' => $this->template->id,
                'filename' => $filename,
                'path' => 'email-templates/attachments/' . $name,
                'filesize' => $filesize,
                'mime_type' => $mime,
            ]);

            $stored++;
        }

        $this->attachments = [null];

        return $stored;
    }

    public function removeAttachment(int $id): void
    {
        abort_unless(NotificationTemplateResource::canEdit($this->template), 403);

        $file = EmailTemplateAttachment::where('template_id', $this->template->id)->findOrFail($id);

        // The row goes either way: a file already gone from disk must not leave a record
        // pointing at nothing.
        if (is_file($file->absolutePath())) {
            @unlink($file->absolutePath());
        }

        $file->delete();

        Notification::make()->title('Attachment removed')->success()->send();
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

        // The translations, one row per active language. A version left entirely blank is
        // removed rather than stored, so "no translation" and "an empty translation" stay
        // the same thing and the default keeps sending.
        foreach ($this->locales as $locale => $version) {
            $subject = trim((string) ($version['subject'] ?? ''));
            $body = trim((string) ($version['body'] ?? ''));
            $where = ['notification_template_id' => $this->template->id, 'locale' => $locale];

            if ($subject === '' && $body === '') {
                TemplateLocale::where($where)->delete();

                continue;
            }

            TemplateLocale::updateOrCreate($where, ['subject' => $subject, 'body' => $body]);
        }

        // The reference takes the chosen files with Save Changes rather than on a button
        // of their own, so this is where they land.
        $files = $this->storeQueuedAttachments();

        Notification::make()->title('Template saved')
            ->body($files ? $files . ' attachment(s) added.' : null)
            ->success()->send();
    }

    /**
     * The objects a template key is handed, from NotificationHelper's own call sites. The
     * body is rendered with exactly this data and nothing else - notably not `$user`, so
     * a client's details are reached through the record that belongs to them.
     *
     * @var array<string, array<int, string>>
     */
    private const CARRIES = [
        'new_order_created' => ['order', 'items', 'total'],
        'new_invoice_created' => ['invoice'],
        'invoice_paid' => ['invoice'],
        'invoice_reminder' => ['invoice'],
        'new_server_created' => ['service'],
        'server_suspended' => ['service'],
        'server_terminated' => ['service'],
        'service_cancellation_received' => ['service'],
        'new_ticket_message' => ['ticketMessage'],
    ];

    /** What each carried name is, so its fields can be read off its table. */
    private const MODELS = [
        'order' => \App\Models\Order::class,
        'invoice' => \App\Models\Invoice::class,
        'service' => \App\Models\Service::class,
        'ticketMessage' => \App\Models\TicketMessage::class,
        'item' => \App\Models\Service::class,
        'items' => \App\Models\Service::class,
    ];

    /** Columns that are plumbing rather than anything to put in an email. */
    private const HIDDEN = [
        'password', 'remember_token', 'tfa_secret', 'updated_at', 'deleted_at',
        'settingable_id', 'settingable_type', 'reference_id', 'reference_type',
    ];

    /**
     * The reference's Available Merge Fields panel. It lists everything this template can
     * resolve rather than only what its current body happens to use, which is the point of
     * the panel: the reference's Client Signup Email shows the whole client vocabulary
     * while using four of it (Leandro, 2026-09-11).
     *
     * @return array{groups: array<int, array{heading: string, rows: array<int, array{label: string, token: string}>}>, links: array<int, string>}
     */
    public function mergeFields(): array
    {
        $sources = [$this->body];

        $shipped = \Database\Seeders\EmailTemplateSeeder::mapping[$this->template->key] ?? null;

        if ($shipped) {
            $sources[] = (string) ($shipped['body'] ?? '');
            $sources[] = (string) ($shipped['subject'] ?? '');
            $sources[] = (string) ($shipped['in_app_body'] ?? '');
        }

        preg_match_all('/\{\{\s*(.+?)\s*\}\}/s', implode("\n", $sources), $matches);

        // `route(...)` calls are links rather than fields to paste, so they are listed
        // apart - the reference does the same with its conditional and loop examples.
        $links = [];
        $used = [];

        foreach (array_unique($matches[1] ?? []) as $token) {
            if (str_starts_with($token, 'route(')) {
                $links[] = '{{ ' . $token . ' }}';
            } else {
                $used[] = '{{ ' . $token . ' }}';
            }
        }

        sort($links);

        $carries = self::CARRIES[$this->template->key] ?? [];

        // Nothing mapped: fall back to what the body itself reaches for, which is at least
        // true of this template even if it is not the whole vocabulary.
        if ($carries === []) {
            sort($used);

            return [
                'groups' => $used === [] ? [] : [['heading' => 'Available Fields', 'rows' => array_map(
                    fn (string $token): array => ['label' => static::labelFor($token), 'token' => $token],
                    $used,
                )]],
                'links' => $links,
            ];
        }

        $groups = [];
        $clientRoot = null;

        foreach ($carries as $name) {
            $model = self::MODELS[$name] ?? null;

            if (!$model) {
                // A scalar the helper passes ready-made, e.g. the order's formatted total.
                $groups[] = ['heading' => Str::of($name)->headline(), 'rows' => [
                    ['label' => Str::of($name)->headline(), 'token' => '{{ $' . $name . ' }}'],
                ]];

                continue;
            }

            // `items` is a collection - each row is reached inside a @foreach, so the
            // tokens are written against the loop variable rather than the collection.
            $variable = $name === 'items' ? 'item' : $name;
            $rows = static::rowsFor($model, '$' . $variable);

            if ($rows === []) {
                continue;
            }

            $groups[] = [
                'heading' => Str::of($name)->headline() . ' Related',
                'rows' => $rows,
            ];

            $clientRoot ??= $variable;
        }

        // The reference's Client Related block. Nothing hands a template the user, so the
        // client is reached through the record that belongs to them.
        if ($clientRoot) {
            $prefix = '$' . $clientRoot . ($clientRoot === 'ticketMessage' ? '->ticket->user' : '->user');
            $rows = static::rowsFor(\App\Models\User::class, $prefix);

            if ($rows !== []) {
                array_unshift($groups, ['heading' => 'Client Related', 'rows' => $rows]);
            }
        }

        return ['groups' => $groups, 'links' => $links];
    }

    /**
     * One row per column the model really has, named the way the reference names them.
     *
     * @return array<int, array{label: string, token: string}>
     */
    private static function rowsFor(string $model, string $prefix): array
    {
        try {
            $columns = Schema::getColumnListing((new $model)->getTable());
        } catch (\Throwable $e) {
            return [];
        }

        $rows = [];

        foreach ($columns as $column) {
            if (in_array($column, self::HIDDEN, true)) {
                continue;
            }

            $rows[] = [
                'label' => Str::of($column)->replace('_id', '')->headline(),
                'token' => '{{ ' . $prefix . '->' . $column . ' }}',
            ];
        }

        return $rows;
    }

    /** The reference names each tag; the name is read out of the tag itself. */
    public static function labelFor(string $token): string
    {
        preg_match('/([A-Za-z_]+)(?:\(\))?\s*\}\}/', $token, $m);

        return Str::of($m[1] ?? trim($token, '{}$ '))->headline();
    }

    protected function getViewData(): array
    {
        return [
            'localeNames' => AddNewClient::languages(),
            'storedAttachments' => EmailTemplateAttachment::where('template_id', $this->template->id)
                ->orderBy('id')->get(),
        ];
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
