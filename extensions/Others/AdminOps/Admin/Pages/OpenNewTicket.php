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
 *
 * Creating the ticket is core's own flow — a Ticket plus its first TicketMessage authored
 * by the signed-in admin, attachments stored where the client portal stores its own — so a
 * ticket opened here is indistinguishable from one a client opened.
 */
class OpenNewTicket extends Page
{
    use WithFileUploads;

    protected string $view = 'adminops::pages.open-new-ticket';

    protected static ?string $slug = 'open-ticket';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public ?int $client = null;

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

    public function insert(string $text): void
    {
        $this->message = rtrim($this->message) === '' ? $text : rtrim($this->message) . "\n\n" . $text;
        $this->inserting = null;
    }

    public function create(): void
    {
        // The None radio submits '' — normalised before the exists rule sees it.
        $this->service = $this->service ?: null;

        $departments = (array) config('settings.ticket_departments');

        $this->validate([
            'client' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'department' => $departments !== [] ? 'required|in:' . implode(',', $departments) : 'nullable',
            'priority' => 'in:low,medium,high',
            'attachments.*' => 'file|max:10240',
            // The ids come from the client side, so the picked service must really be
            // this client's — not just any service id (issue #20).
            'service' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('services', 'id')->where('user_id', $this->client),
            ],
        ], attributes: ['client' => 'client', 'service' => 'related service']);

        $ticket = DB::transaction(function (): Ticket {
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
                $attachment->storeAs('tickets/uploads', $name);

                $message->attachments()->create([
                    'uuid' => Str::uuid(),
                    'filename' => $attachment->getClientOriginalName(),
                    'path' => 'tickets/uploads/' . $name,
                    'filesize' => $attachment->getSize(),
                    'mime_type' => (string) $attachment->getMimeType(),
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

        Notification::make()->title('Ticket #' . $ticket->id . ' opened')->success()->send();
        $this->redirect(TicketResource::getUrl('edit', ['record' => $ticket->id]));
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
