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

    /**
     * The reference's six statuses (Leandro's dropdown screenshot, 2026-09-06), mapped
     * onto what this store can honestly hold. Open/Answered/Closed are core's own three;
     * On Hold and In Progress are extra states the column carries fine (nothing in core
     * sweeps them — the inactivity cron only closes 'replied'). Customer-Reply is core's
     * behaviour, not a stored value: a client reply sets 'open', so picking it stores
     * 'open', and {@see displayStatus()} shows it whenever an open ticket's last word
     * was the customer's — exactly what the label means.
     */
    public const STATUSES = [
        'open' => 'Open',
        'replied' => 'Answered',
        'customer_reply' => 'Customer-Reply',
        'on_hold' => 'On Hold',
        'in_progress' => 'In Progress',
        'closed' => 'Closed',
    ];

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

    /** The note tab's own status select — the reference's "- Set Status -". */
    public string $noteStatus = '';

    /** The Options tab's editable fields. */
    public string $subject = '';

    public string $department = '';

    public string $priority = 'medium';

    public string $assignedTo = '';

    /** Options: the reference's Status, CC Recipients, Prevent Client Closure, Merge. */
    public string $optStatus = 'open';

    /** The reference's Client Name picker — the ticket's owner, reassignable. */
    public string $clientId = '';

    public string $ccRecipients = '';

    public bool $preventClosure = false;

    public string $mergeId = '';

    /** The reference's Tag Cloud: free-text tags on the ticket, and its "Add a Tag…" box. */
    public array $tags = [];

    public string $tagInput = '';

    /** The reference's Watch Ticket / Ticket Watchers: staff ids copied on every reply. */
    public array $watchers = [];

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
        $this->optStatus = $this->displayStatus();
        $this->clientId = (string) ($this->ticket->user_id ?? '');

        if (Schema::hasTable('ext_ticket_meta')) {
            $meta = DB::table('ext_ticket_meta')->where('ticket_id', $this->ticket->id)->first();
            $this->ccRecipients = (string) ($meta->cc ?? '');
            $this->preventClosure = (bool) ($meta->prevent_closure ?? false);
            $this->tags = array_values(array_filter(array_map('trim', explode(',', (string) ($meta->tags ?? '')))));
            $this->watchers = array_map('intval', json_decode((string) ($meta->watchers ?? '[]'), true) ?: []);
        }
    }

    /**
     * Write a subset of the ticket's AdminOps-owned meta columns.
     *
     * The Options tab's Save rewrites `cc` and `prevent_closure` wholesale; the rail's
     * controls each own one column, so they must not carry the others' values along with
     * them — tagging a ticket should not save a CC list someone is mid-way through typing.
     */
    private function writeMeta(array $values): void
    {
        if (! Schema::hasTable('ext_ticket_meta')) {
            Notification::make()->title('The ticket meta table is not migrated')->danger()->send();

            return;
        }

        DB::table('ext_ticket_meta')->updateOrInsert(
            ['ticket_id' => $this->ticket->id],
            $values + ['updated_at' => now(), 'created_at' => now()],
        );
    }

    /** The status as the reference names it — Customer-Reply when an open ticket's last
     *  word was the customer's, the stored value otherwise. */
    public function displayStatus(): string
    {
        if ($this->ticket->status === 'open') {
            $last = $this->ticket->messages()->latest()->first();

            if ($last && $last->user_id === $this->ticket->user_id && $this->ticket->messages()->count() > 1) {
                return 'customer_reply';
            }
        }

        return (string) $this->ticket->status;
    }

    /** A picked status key to the value the column stores — Customer-Reply IS open here. */
    private function storableStatus(string $status): ?string
    {
        if (!array_key_exists($status, self::STATUSES)) {
            return null;
        }

        return $status === 'customer_reply' ? 'open' : $status;
    }

    /** The header strip's status select — writes immediately, as the reference's does. */
    public function setStatus(string $status): void
    {
        $store = $this->storableStatus($status);

        if ($store === null) {
            return;
        }

        $this->ticket->update(['status' => $store]);
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
            'replyStatus' => 'in:' . implode(',', array_keys(self::STATUSES)),
            'attachments.*' => 'file|max:102400',
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
            'status' => $this->storableStatus($this->replyStatus) ?? 'replied',
            'department' => $this->department ?: null,
            'priority' => $this->priority,
            'assigned_to' => $this->assignedTo !== '' ? (int) $this->assignedTo : null,
        ]);

        // The Options tab's CC Recipients get a copy of every staff reply — that is
        // what the reference's CC list is for, and the rail's watchers ride the same
        // path, which is what makes Watch Ticket mean something. Per-address catch: one
        // dead mailbox must not lose the reply for the rest.
        foreach (array_unique([...$this->ccList(), ...$this->watcherEmails()]) as $address) {
            try {
                \App\Helpers\NotificationHelper::sendSystemEmailNotification(
                    '[Ticket #' . $this->ticket->id . '] ' . $this->ticket->subject,
                    nl2br(e($this->reply)),
                    email: $address,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('EditTicket: CC copy failed', ['to' => $address, 'error' => $e->getMessage()]);
            }
        }

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

        // The reference's selects travel with the note the way the reply row's do;
        // "- Set Status -" left empty means leave the status be.
        $updates = [
            'department' => $this->department ?: null,
            'priority' => $this->priority,
            'assigned_to' => $this->assignedTo !== '' ? (int) $this->assignedTo : null,
        ];

        if ($this->noteStatus !== '' && ($store = $this->storableStatus($this->noteStatus)) !== null) {
            $updates['status'] = $store;
        }

        $this->ticket->update($updates);

        $this->reset(['note', 'noteStatus']);
        Notification::make()->title('Note added')->success()->send();

        if ($this->returnToList) {
            $this->redirect(SupportTickets::getUrl());
        }
    }

    /**
     * The watching staff's addresses, minus whoever is replying — a reply should not mail
     * its own author a copy of itself. @return array<int, string>
     */
    private function watcherEmails(): array
    {
        if ($this->watchers === []) {
            return [];
        }

        return User::whereIn('id', $this->watchers)
            ->where('id', '!=', Auth::id())
            ->pluck('email')
            ->all();
    }

    /** The stored CC list, split and validated once. @return array<int, string> */
    private function ccList(): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $this->ccRecipients)),
            fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }

    /** The Options tab's Save — the reference's full field set. */
    public function saveOptions(): void
    {
        $this->validate([
            'subject' => 'required|string|max:255',
            'priority' => 'in:low,medium,high',
            'optStatus' => 'in:' . implode(',', array_keys(self::STATUSES)),
            'mergeId' => 'nullable|integer',
            'clientId' => 'required|exists:users,id',
        ], attributes: ['optStatus' => 'status', 'mergeId' => 'merge ticket', 'clientId' => 'client name']);

        // CC addresses validated the same way Open New Ticket validates its own.
        foreach (array_filter(array_map('trim', explode(',', $this->ccRecipients))) as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $this->addError('ccRecipients', '"' . $address . '" is not a valid email address.');

                return;
            }
        }

        // The reference's Merge Ticket ("# to combine"): the other ticket's messages
        // move into this thread and the emptied ticket goes. Same-client only — merging
        // two customers' tickets would show one of them the other's conversation.
        if ($this->mergeId !== '') {
            $source = Ticket::find((int) $this->mergeId);

            if (!$source || $source->id === $this->ticket->id) {
                $this->addError('mergeId', 'No other ticket #' . $this->mergeId . ' exists.');

                return;
            }

            if ($source->user_id !== $this->ticket->user_id) {
                $this->addError('mergeId', 'Ticket #' . $source->id . ' belongs to a different client — merging would cross their conversations.');

                return;
            }

            DB::transaction(function () use ($source): void {
                $source->messages()->update(['ticket_id' => $this->ticket->id]);
                $source->delete();
            });
        }

        $this->ticket->update([
            'subject' => $this->subject,
            'department' => $this->department ?: null,
            'priority' => $this->priority,
            'assigned_to' => $this->assignedTo !== '' ? (int) $this->assignedTo : null,
            'status' => $this->storableStatus($this->optStatus) ?? $this->ticket->status,
            'user_id' => (int) $this->clientId,
        ]);

        if (Schema::hasTable('ext_ticket_meta')) {
            DB::table('ext_ticket_meta')->updateOrInsert(
                ['ticket_id' => $this->ticket->id],
                ['cc' => trim($this->ccRecipients) ?: null, 'prevent_closure' => $this->preventClosure, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $merged = $this->mergeId !== '';
        $this->mergeId = '';

        Notification::make()->title('Ticket updated')
            ->body($merged ? 'The other ticket\'s messages are in this thread now.' : null)
            ->success()->send();
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

    /**
     * The reference's "me" beside Assigned To: take the ticket in one click.
     *
     * Writes only `assigned_to`, not the whole Options form — picking a ticket up should not
     * quietly save a half-filled subject or a department someone was mid-way through changing.
     */
    public function assignToMe(): void
    {
        $this->ticket->update(['assigned_to' => Auth::id()]);
        $this->ticket->refresh();
        $this->assignedTo = (string) $this->ticket->assigned_to;

        Notification::make()->title('Assigned to you')->success()->send();
    }

    /**
     * The rail's Department / Assigned To / Priority selects.
     *
     * The reference's sidebar sets these on change rather than behind a Save, and they are
     * the same three fields the Options tab holds — so the two views share the properties
     * and this writes only those three columns. Options' own Save still needs pressing for
     * everything else it owns.
     */
    public function saveAssignment(): void
    {
        $this->validate(['priority' => 'in:low,medium,high']);

        $this->ticket->update([
            'department' => $this->department ?: null,
            'priority' => $this->priority,
            'assigned_to' => $this->assignedTo !== '' ? (int) $this->assignedTo : null,
        ]);
        $this->ticket->refresh();

        Notification::make()->title('Ticket updated')->success()->send();
    }

    /** The reference's "Add a Tag…" box. Tags are per-ticket free text, deduplicated. */
    public function addTag(): void
    {
        $tag = trim($this->tagInput);

        if ($tag === '') {
            return;
        }

        // Commas separate stored tags, so one inside a tag would split it in two on load.
        // Collapsing the run afterwards keeps "billing, urgent" from becoming a tag with a
        // double space in it, which then fails to match when you click it off again.
        $tag = trim(preg_replace('/\s+/', ' ', str_replace(',', ' ', $tag)));

        if (! in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
            $this->writeMeta(['tags' => implode(',', $this->tags)]);
        }

        $this->tagInput = '';
    }

    public function removeTag(string $tag): void
    {
        $this->tags = array_values(array_filter($this->tags, fn (string $t): bool => $t !== $tag));
        $this->writeMeta(['tags' => implode(',', $this->tags) ?: null]);
    }

    /** The reference's Watch Ticket button — a watcher is copied on every reply. */
    public function toggleWatch(): void
    {
        $id = (int) Auth::id();

        $this->watchers = in_array($id, $this->watchers, true)
            ? array_values(array_filter($this->watchers, fn (int $w): bool => $w !== $id))
            : [...$this->watchers, $id];

        $this->writeMeta(['watchers' => json_encode($this->watchers)]);

        Notification::make()
            ->title(in_array($id, $this->watchers, true) ? 'Watching this ticket' : 'No longer watching')
            ->success()->send();
    }

    /**
     * An audit row as a sentence a human reads, for the Client Log's mixed record types.
     *
     * Only scalars are shown and only the first few: an audit of a created Service carries
     * two dozen columns, and printing them all is how the tab came to be a wall of JSON.
     */
    private static function describeAudit(object $row): string
    {
        if ($row->event === 'deleted') {
            return 'Deleted';
        }

        $values = json_decode((string) ($row->new_values ?? '[]'), true) ?: [];
        $parts = [];

        foreach ($values as $key => $value) {
            if (count($parts) === 4) {
                $parts[] = '…';
                break;
            }

            if ($value === null || is_array($value) || $key === 'id' || str_ends_with((string) $key, '_at')) {
                continue;
            }

            $shown = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;

            // Audits store model references as FQCNs; the namespace is noise to a reader.
            if (str_contains($shown, '\\') && str_ends_with((string) $key, '_type')) {
                $shown = class_basename($shown);
            }

            $parts[] = Str::headline((string) $key) . ': ' . Str::limit($shown, 40);
        }

        return $parts === []
            ? ($row->event === 'created' ? 'Created' : 'Updated')
            : ($row->event === 'created' ? 'Created — ' : '') . implode(', ', $parts);
    }

    protected function getViewData(): array
    {
        $lastReply = $this->ticket->messages()->latest()->first();

        return [
            'messages' => $this->ticket->messages()->with(['user', 'attachments'])->latest()->get(),
            'departments' => (array) config('settings.ticket_departments'),
            // Two staff accounts really can share a display name (this install has two
            // "Admin You"), which made the Assigned To list read as a duplicate. The
            // email disambiguates — but only for the names that actually collide, so
            // the normal case stays clean (Leandro's circle, 2026-09-06).
            'admins' => (function (): \Illuminate\Support\Collection {
                $staff = User::whereNotNull('role_id')->orderBy('first_name')->orderBy('last_name')->get();
                $names = $staff->countBy(fn ($u) => trim($u->first_name . ' ' . $u->last_name));

                return $staff->map(function ($u) use ($names): array {
                    $name = trim($u->first_name . ' ' . $u->last_name);

                    return [
                        'id' => $u->id,
                        'label' => ($name ?: $u->email) . (($names[$name] ?? 0) > 1 ? ' (' . $u->email . ')' : ''),
                    ];
                });
            })(),
            // The reference's Client Name is a picker, not a label — the ticket's owner
            // can be corrected from here.
            'clients' => User::whereNull('role_id')->orderBy('first_name')->orderBy('last_name')->limit(200)->get(),
            // The reference's Ticket Info panel down the left: who this is for, and who
            // has touched it, without opening a dropdown to find out.
            'owner' => $this->ticket->user,
            // The rail's Ticket Watchers list, in the order the button added them.
            'watcherUsers' => $this->watchers === []
                ? collect()
                : User::whereIn('id', $this->watchers)->get()
                    ->sortBy(fn ($u) => array_search($u->id, $this->watchers, true))
                    ->values(),
            'staffParticipants' => $this->ticket->messages()
                ->with('user:id,first_name,last_name,email,role_id')
                ->get()
                ->pluck('user')
                ->filter(fn ($u) => $u && $u->role_id)
                ->unique('id')
                ->values(),
            'canned' => Schema::hasTable('canned_responses')
                ? \Paymenter\Extensions\Others\TicketTools\Models\CannedResponse::where('active', true)->orderBy('title')->get()
                : collect(),
            'notes' => Schema::hasTable('ticket_notes')
                ? \Paymenter\Extensions\Others\TicketTools\Models\TicketNote::with('author')
                    ->where('ticket_id', $this->ticket->id)->latest()->get()
                : collect(),
            'otherTickets' => Ticket::where('user_id', $this->ticket->user_id)
                ->where('id', '!=', $this->ticket->id)->latest()->limit(50)
                ->with(['messages' => fn ($q) => $q->latest(), 'assignedTo'])->get(),
            // The reference's Log tab speaks sentences ("New Support Ticket Opened
            // (by X)"), not raw JSON diffs — the audits humanised.
            'logRows' => Schema::hasTable('audits')
                ? DB::table('audits')->where('auditable_type', Ticket::class)
                    ->where('auditable_id', $this->ticket->id)->orderByDesc('id')->limit(50)->get()
                    ->map(function ($row): array {
                        $actor = $row->user_id
                            ? User::find($row->user_id)
                            : null;
                        $by = $actor
                            ? ' (by ' . (trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) ?: $actor->email) . ')'
                            : '';
                        $new = json_decode($row->new_values ?? '[]', true) ?: [];

                        $action = match (true) {
                            $row->event === 'created' => 'New Support Ticket Opened',
                            isset($new['status']) => 'Status changed to ' . (self::STATUSES[$new['status']] ?? ucfirst($new['status'])),
                            isset($new['assigned_to']) => 'Ticket assignment changed',
                            isset($new['department']) => 'Department changed to ' . $new['department'],
                            isset($new['priority']) => 'Priority changed to ' . ucfirst($new['priority']),
                            isset($new['subject']) => 'Subject changed',
                            $row->event === 'deleted' => 'Ticket deleted',
                            default => ucfirst($row->event),
                        };

                        return ['at' => $row->created_at, 'action' => $action . $by];
                    })
                : collect(),
            // The reference's Client Log tab: what this ticket's client has been doing.
            // The rows were being printed as their raw `new_values` JSON, which overflowed
            // the panel with `{"reference_id":98,"reference_type":"App\\Models\\Service"…}`
            // and told the reader nothing. Same humanising as the Log tab beside it.
            'clientLogRows' => Schema::hasTable('audits')
                ? DB::table('audits')->where('user_id', $this->ticket->user_id)
                    ->orderByDesc('id')->limit(50)->get()
                    ->map(fn ($row): array => [
                        'at' => $row->created_at,
                        'event' => ucfirst((string) $row->event),
                        'record' => class_basename((string) $row->auditable_type) . ' #' . $row->auditable_id,
                        'action' => self::describeAudit($row),
                    ])
                : collect(),
            'rendered' => $this->preview
                ? Str::markdown(e($this->reply ?: '*Nothing to preview yet.*'))
                : null,
            'lastReplyAgo' => $lastReply?->created_at?->diffForHumans(),
            'listUrl' => SupportTickets::getUrl(),
        ];
    }
}
