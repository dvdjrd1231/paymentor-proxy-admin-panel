<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Open New Ticket, to its screenshot: the client picker with the services table
 * beneath it, subject, department and priority, the message box with Insert Knowledgebase
 * Link and Insert Predefined Reply, attachments, and the one blue button.
 */
class OpenNewTicket extends Page
{
    use WithFileUploads;

    protected string $view = 'adminops::pages.open-new-ticket';

    protected static ?string $slug = 'open-ticket';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** A real client's id, or {@see self::GUEST} for someone who has no account yet. */
    #[Url]
    public ?int $client = null;

    /**
     * The client picker's "not a registered client" choice, which the reference offers so
     * a ticket can be opened for someone who wrote in before they ever signed up.
     */
    public const GUEST = 0;

    public string $guestName = '';

    public string $guestEmail = '';

    /**
     * The related service the reference's radio column picks — null/'' is its "None".
     * Untyped: the None radio's value is the empty string, and Livewire would refuse
     * to write '' into a ?int property.
     */
    public $service = null;

    public string $subject = '';

    public string $department = '';

    public string $priority = 'medium';

    public bool $sendEmail = true;

    /**
     * The reference's CC Recipients (user request, 2026-09-04): comma-separated
     * addresses that each get an emailed copy of the opening message. Core tickets
     * carry no CC column, so the copy is the feature — sent through the same
     * system-mail path everything else uses, and logged in Email Logs like any mail.
     */
    public string $ccRecipients = '';

    public string $message = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public bool $preview = false;

    /** Which insert modal is showing — kb | reply — or null. */
    public ?string $inserting = null;

    public static function canAccess(): bool
    {
        return TicketResource::canCreate();
    }

    public function getTitle(): string
    {
        return 'Open New Ticket';
    }

    /** A picked service belongs to the previous client; a new client starts at None. */
    public function updatedClient(): void
    {
        $this->service = null;
    }

    /** Whether the picker is on "not a registered client". */
    public function isGuest(): bool
    {
        return $this->client === self::GUEST;
    }

    public function insert(string $text): void
    {
        $this->message = rtrim($this->message) === '' ? $text : rtrim($this->message) . "\n\n" . $text;
        $this->inserting = null;
    }

    /**
     * The account behind a "not a registered client" ticket: the one that already holds
     * this address, or a new one. Never a staff account - a ticket opened against an
     * admin's own address would land in a client area they cannot see.
     */
    private function resolveGuest(): \App\Models\User
    {
        $email = strtolower(trim($this->guestEmail));

        if ($existing = \App\Models\User::whereRaw('LOWER(email) = ?', [$email])->whereNull('role_id')->first()) {
            return $existing;
        }

        $name = trim($this->guestName);
        $space = strrpos($name, ' ');

        return \App\Models\User::create([
            'first_name' => $space === false ? $name : substr($name, 0, $space),
            'last_name' => $space === false ? '' : substr($name, $space + 1),
            'email' => $email,
            // No usable password: they set one through the reset link like any other
            // client, and until then the account exists only to carry the ticket.
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40)),
        ]);
    }

    public function create(): void
    {
        // The None radio submits '' — normalised before the exists rule sees it.
        $this->service = $this->service ?: null;

        $departments = (array) config('settings.ticket_departments');

        // Split before validating, so the rule reads addresses, not one long string.
        $ccList = array_values(array_filter(array_map('trim', explode(',', $this->ccRecipients))));

        // A ticket belongs to a user - core's tickets.user_id is a non-null foreign key,
        // and every reply, notification and client-area view reads through it. So the
        // reference's "not a registered client" is honoured by giving the person an
        // account rather than by leaving the ticket ownerless. The account is made inside
        // the transaction below, not here: a form that fails on some later field must not
        // leave a client behind.
        $guest = $this->isGuest();

        $this->validate([
            'guestName' => $guest ? 'required|string|max:255' : 'nullable',
            'guestEmail' => $guest ? 'required|email|max:255' : 'nullable',
            'client' => $guest ? 'present' : 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'department' => $departments !== [] ? 'required|in:' . implode(',', $departments) : 'nullable',
            'priority' => 'in:low,medium,high',
            'attachments.*' => 'file|max:102400',
            // The ids come from the client side, so the picked service must really be
            // this client's — not just any service id (issue #20).
            'service' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('services', 'id')->where('user_id', $this->client),
            ],
        ], attributes: [
            'client' => 'client',
            'service' => 'related service',
            'guestName' => 'name',
            'guestEmail' => 'email address',
        ]);

        foreach ($ccList as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $this->addError('ccRecipients', '"' . $address . '" is not a valid email address.');

                return;
            }
        }

        $ticket = DB::transaction(function () use ($guest): Ticket {
            if ($guest) {
                $this->client = $this->resolveGuest()->id;
            }

            $ticket = Ticket::create([
                'subject' => $this->subject,
                'status' => 'replied',
                'priority' => $this->priority,
                'department' => $this->department ?: null,
                'user_id' => $this->client,
                // The reference's radio column: the ticket carries the service it is
                // about, on core's own ticket column (issue #20).
                'service_id' => $this->service ?: null,
                'assigned_to' => Auth::id(),
            ]);

            $message = $ticket->messages()->create([
                'user_id' => Auth::id(),
                'message' => $this->message,
            ]);

            foreach ($this->attachments as $attachment) {
                // Stored exactly as the client portal stores its own uploads.
                $name = Str::ulid() . '.' . $attachment->getClientOriginalExtension();

                // Read the file's own facts *before* storing it. storeAs() moves the upload
                // out of livewire-tmp, so asking for its size afterwards reads a path that
                // no longer exists — "Unable to retrieve the file_size for file at location:
                // livewire-tmp/…", which surfaced as the "Error while loading page" toast
                // whenever a ticket was opened with an attachment.
                $filename = $attachment->getClientOriginalName();
                $filesize = $attachment->getSize();
                $mime = (string) $attachment->getMimeType();

                $attachment->storeAs('tickets/uploads', $name);

                $message->attachments()->create([
                    'uuid' => Str::uuid(),
                    'filename' => $filename,
                    'path' => 'tickets/uploads/' . $name,
                    'filesize' => $filesize,
                    'mime_type' => $mime,
                ]);
            }

            return $ticket;
        });

        if ($this->sendEmail) {
            try {
                \App\Helpers\NotificationHelper::sendNotification('ticket_created', ['ticket' => $ticket], $ticket->user);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('OpenNewTicket: email failed', ['error' => $e->getMessage()]);
            }
        }

        // Each CC gets a copy of the opening message — logged in Email Logs like any
        // other mail. One bad mailbox must not stop the rest, hence per-address catch.
        foreach ($ccList as $address) {
            try {
                \App\Helpers\NotificationHelper::sendSystemEmailNotification(
                    '[Ticket #' . $ticket->id . '] ' . $this->subject,
                    nl2br(e($this->message)),
                    email: $address,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('OpenNewTicket: CC email failed', ['to' => $address, 'error' => $e->getMessage()]);
            }
        }

        Notification::make()->title('Ticket #' . $ticket->id . ' opened')->success()->send();
        $this->redirect(EditTicket::getUrl(['record' => $ticket->id]));
    }

    protected function getViewData(): array
    {
        $user = $this->client ? User::with('properties')->find($this->client) : null;

        $kb = [];
        if (class_exists(\Paymenter\Extensions\Others\Knowledgebase\Models\KbArticle::class)) {
            try {
                $kb = \Paymenter\Extensions\Others\Knowledgebase\Models\KbArticle::query()
                    ->orderBy('title')->limit(100)->get(['id', 'title', 'slug'])->all();
            } catch (\Throwable $e) {
            }
        }

        $replies = [];
        if (class_exists(\Paymenter\Extensions\Others\TicketTools\Models\CannedResponse::class)) {
            try {
                $replies = \Paymenter\Extensions\Others\TicketTools\Models\CannedResponse::query()
                    ->where('active', true)->orderBy('title')->limit(100)->get(['id', 'title', 'body'])->all();
            } catch (\Throwable $e) {
            }
        }

        return [
            'clients' => User::whereNull('role_id')->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'email']),
            'selectedUser' => $user,
            'services' => $user?->services()->with('product')->latest()->limit(50)->get() ?? collect(),
            'departments' => (array) config('settings.ticket_departments'),
            'kbArticles' => $kb,
            'cannedReplies' => $replies,
            'rendered' => $this->preview ? Str::markdown($this->message ?: '*Nothing to preview yet.*') : null,
        ];
    }
}
