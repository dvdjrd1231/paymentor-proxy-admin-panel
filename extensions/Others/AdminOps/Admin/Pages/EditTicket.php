<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's support-ticket screen (user request, 2026-09-04, screenshots): the
 * "#id — subject" strip with the status select and Close, the Add Reply / Add Note /
 * Other Tickets / Options / Log tabs, the reply editor over the Set Department /
 * Set Assignment / Set Priority / status row, Insert Predefined Reply, attachments,
 * Return to Ticket List — and the message thread beneath, newest first, each entry
 * with its poster and Delete.
 *
 * Everything writes core's own columns and rows: replies are TicketMessages exactly as
 * OpenNewTicket makes them, notes are TicketTools' ticket_notes, the selects hit the
 * ticket's real department/priority/assigned_to/status, and Log reads the audits core
 * already records for this ticket.
 */
class EditTicket extends Page
{
    use WithFileUploads;

    protected string $view = 'adminops::pages.edit-ticket';

    protected static ?string $slug = 'ticket';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Same reasoning as {@see ClientSummary::$customer} — not `$record`. */
    public Ticket $ticket;

    #[Url(as: 'view')]
    public string $tab = 'reply';

    public string $reply = '';

    /** The reference's Preview toggle: renders the reply's markdown server-side. */
    public bool $preview = false;

    /** In-place message editing — the reference's Edit button on each thread entry. */
    public ?int $editingMessage = null;

    public string $editingText = '';

    /** The status the ticket takes when the reply sends — the reference's fourth select. */
    public string $replyStatus = 'replied';

    public bool $returnToList = true;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public string $note = '';

    /** The Options tab's editable fields. */
    public string $subject = '';

    public string $department = '';

    public string $priority = 'medium';

    public string $assignedTo = '';

    public ?string $confirmingDelete = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public static function canAccess(): bool
    {
        return TicketResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Support Tickets';
    }

    public function mount(int|string $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->ticket = Ticket::with(['user', 'assignedTo', 'service.product'])->findOrFail($record);
        $this->subject = (string) $this->ticket->subject;
        $this->department = (string) ($this->ticket->department ?? '');
        $this->priority = (string) ($this->ticket->priority ?: 'medium');
        $this->assignedTo = (string) ($this->ticket->assigned_to ?? '');
    }

    /** The header strip's status select — writes immediately, as the reference's does. */
    public function setStatus(string $status): void
    {
        if (!in_array($status, ['open', 'replied', 'closed'], true)) {
            return;
        }

        $this->ticket->update(['status' => $status]);
        Notification::make()->title('Status updated')->success()->send();
    }

    /** The reference's Insert Predefined Reply — appends the canned response's body. */
    public function insertCanned(string $id): void
    {
        if (!ctype_digit($id) || !class_exists(\Paymenter\Extensions\Others\TicketTools\Models\CannedResponse::class)) {
            return;
        }

        $canned = \Paymenter\Extensions\Others\TicketTools\Models\CannedResponse::find((int) $id);

        if ($canned) {
            $this->reply = trim($this->reply . "\n\n" . $canned->body);
        }
    }

    public function sendReply(): void
    {
        $this->validate([
            'reply' => 'required|string',
            'replyStatus' => 'in:open,replied,closed',
            'attachments.*' => 'file|max:10240',
        ], attributes: ['reply' => 'message']);

        $message = $this->ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => $this->reply,
        ]);

        foreach ($this->attachments as $attachment) {
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

        // The bottom row's selects travel with the reply, the reference's behaviour.
        $this->ticket->update([
            'status' => $this->replyStatus,
            'department' => $this->department ?: null,
            'priority' => $this->priority,
            'assigned_to' => $this->assignedTo !== '' ? (int) $this->assignedTo : null,
        ]);

        $this->reset(['reply', 'attachments']);

        Notification::make()->title('Reply sent')->success()->send();

        if ($this->returnToList) {
            $this->redirect(SupportTickets::getUrl());
        }
    }

    /** The Add Note tab — TicketTools' real ticket_notes rows, staff-only. */
    public function addNote(): void
    {
        if (!Schema::hasTable('ticket_notes')) {
            Notification::make()->title('The Ticket Tools extension is not migrated')->danger()->send();

            return;
        }

        $this->validate(['note' => 'required|string'], attributes: ['note' => 'note']);

        // The table's text column is `body` (TicketTools' own schema).
        \Paymenter\Extensions\Others\TicketTools\Models\TicketNote::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => Auth::id(),
            'body' => $this->note,
        ]);

        $this->reset('note');
        Notification::make()->title('Note added')->success()->send();
    }

    /** The Options tab's Save — the ticket's own columns. */
    public function saveOptions(): void
    {
        $this->validate([
            'subject' => 'required|string|max:255',
            'priority' => 'in:low,medium,high',
        ]);

        $this->ticket->update([
            'subject' => $this->subject,
            'department' => $this->department ?: null,
            'priority' => $this->priority,
            'assigned_to' => $this->assignedTo !== '' ? (int) $this->assignedTo : null,
        ]);

        Notification::make()->title('Ticket updated')->success()->send();
    }

    /** The thread's Edit button: the message text, corrected in place. */
    public function startEditMessage(int $id): void
    {
        $message = $this->ticket->messages()->find($id);

        if ($message) {
            $this->editingMessage = $id;
            $this->editingText = (string) $message->message;
        }
    }

    public function saveMessage(): void
    {
        $this->validate(['editingText' => 'required|string'], attributes: ['editingText' => 'message']);

        $this->ticket->messages()->where('id', $this->editingMessage)->update(['message' => $this->editingText]);
        $this->reset(['editingMessage', 'editingText']);
        Notification::make()->title('Message updated')->success()->send();
    }

    public function deleteMessage(int $id): void
    {
        $message = $this->ticket->messages()->find($id);

        if ($message) {
            $message->attachments()->delete();
            $message->delete();
            Notification::make()->title('Message deleted')->success()->send();
        }
    }

    public function runDeleteTicket(): void
    {
        $this->reset('confirmingDelete');

        if (!TicketResource::canDelete($this->ticket)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $id = $this->ticket->id;
        $this->ticket->messages()->each(function ($message): void {
            $message->attachments()->delete();
            $message->delete();
        });
        $this->ticket->delete();

        Notification::make()->title("Ticket #{$id} deleted")->success()->send();
        $this->redirect(SupportTickets::getUrl());
    }

    protected function getViewData(): array
    {
        $lastReply = $this->ticket->messages()->latest()->first();

        return [
            'messages' => $this->ticket->messages()->with(['user', 'attachments'])->latest()->get(),
            'departments' => (array) config('settings.ticket_departments'),
            'admins' => User::whereNotNull('role_id')->orderBy('first_name')->get(),
            'canned' => Schema::hasTable('canned_responses')
                ? \Paymenter\Extensions\Others\TicketTools\Models\CannedResponse::where('active', true)->orderBy('title')->get()
                : collect(),
            'notes' => Schema::hasTable('ticket_notes')
                ? \Paymenter\Extensions\Others\TicketTools\Models\TicketNote::with('author')
                    ->where('ticket_id', $this->ticket->id)->latest()->get()
                : collect(),
            'otherTickets' => Ticket::where('user_id', $this->ticket->user_id)
                ->where('id', '!=', $this->ticket->id)->latest()->limit(50)->get(),
            'logRows' => Schema::hasTable('audits')
                ? DB::table('audits')->where('auditable_type', Ticket::class)
                    ->where('auditable_id', $this->ticket->id)->orderByDesc('id')->limit(50)->get()
                : collect(),
            // The reference's Client Log tab: what this ticket's client has been doing —
            // the same audit rows the Client Profile's Log tab reads, scoped to them.
            'clientLogRows' => Schema::hasTable('audits')
                ? DB::table('audits')->where('user_id', $this->ticket->user_id)
                    ->orderByDesc('id')->limit(50)->get()
                : collect(),
            'rendered' => $this->preview
                ? Str::markdown(e($this->reply ?: '*Nothing to preview yet.*'))
                : null,
            'lastReplyAgo' => $lastReply?->created_at?->diffForHumans(),
            'listUrl' => SupportTickets::getUrl(),
        ];
    }
}
