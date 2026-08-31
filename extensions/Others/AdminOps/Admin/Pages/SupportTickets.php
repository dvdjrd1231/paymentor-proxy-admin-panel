<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\TicketResource;
use App\Models\Ticket;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Support Tickets, to its screenshots: Search/Filter and Auto Refresh tabs, the
 * With Selected row — Merge, Close, Delete, Block Sender & Delete — the navy grid with
 * the flag column, and the sidebar's status views.
 *
 * ## The reference's eight views on Paymenter's three statuses
 *
 * Paymenter tickets are open, replied or closed, and carry an `assigned_to`. The views map
 * honestly onto that: Flagged is assigned (WHMCS's flag is an assignment), Answered is
 * replied, Customer-Reply is an open ticket whose last word was the customer's, In Progress
 * is assigned-and-not-closed, On Hold has no counterpart and says so by listing nothing.
 */
class SupportTickets extends Page
{
    protected string $view = 'adminops::pages.support-tickets';

    protected static ?string $slug = 'support-tickets';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 100;

    public const VIEWS = [
        'flagged' => 'Flagged Tickets',
        'active' => 'All Active Tickets',
        'open' => 'Open',
        'answered' => 'Answered',
        'customer-reply' => 'Customer-Reply',
        'on-hold' => 'On Hold',
        'in-progress' => 'In Progress',
        'closed' => 'Closed',
    ];

    // Named $tab because a Filament page already owns $view; the URL keeps the
    // reference's ?view= through the alias.
    #[Url(as: 'view')]
    public string $tab = 'active';

    #[Url]
    public string $q = '';

    #[Url]
    public string $dept = '';

    #[Url]
    public string $email = '';

    #[Url]
    public int $page = 1;

    public bool $filter = false;

    /** @var array<int|string, bool> Checked rows, keyed by ticket id. */
    public array $selected = [];

    /** Which bulk action is awaiting its "Are you sure?" — merge | close | delete | block. */
    public ?string $confirming = null;

    public static function canAccess(): bool
    {
        return TicketResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Support Tickets';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function search(): void
    {
        $this->page = 1;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    /** @return array<int> */
    private function picked(): array
    {
        return array_keys(array_filter($this->selected));
    }

    public function ask(string $action): void
    {
        if ($this->picked() === []) {
            Notification::make()->title('Tick at least one ticket first.')->warning()->send();

            return;
        }

        if ($action === 'merge' && count($this->picked()) < 2) {
            // The reference's own words for the same mistake.
            Notification::make()->title('An Error Occurred')
                ->body('You must select at least two tickets for a merge.')
                ->danger()->send();

            return;
        }

        $this->confirming = $action;
    }

    public function run(): void
    {
        $action = $this->confirming;
        $this->confirming = null;
        $tickets = Ticket::whereIn('id', $this->picked())->orderBy('id')->get();

        match ($action) {
            // The reference's merge: everything folds into the oldest ticket; the emptied
            // ones close rather than vanish, so their numbers still resolve.
            'merge' => DB::transaction(function () use ($tickets): void {
                $target = $tickets->first();
                foreach ($tickets->skip(1) as $ticket) {
                    $ticket->messages()->update(['ticket_id' => $target->id]);
                    $ticket->update(['status' => 'closed']);
                }
                $target->update(['status' => 'open']);
                Notification::make()->title('Merged into ticket #' . $target->id)->success()->send();
            }),
            'close' => (function () use ($tickets): void {
                Ticket::whereIn('id', $tickets->pluck('id'))->update(['status' => 'closed']);
                Notification::make()->title(count($tickets) . ' ticket(s) closed')->success()->send();
            })(),
            'delete' => $this->deleteTickets($tickets, block: false),
            'block' => $this->deleteTickets($tickets, block: true),
            default => null,
        };

        $this->selected = [];
    }

    private function deleteTickets($tickets, bool $block): void
    {
        DB::transaction(function () use ($tickets, $block): void {
            foreach ($tickets as $ticket) {
                if ($block && $ticket->user) {
                    // The block flag the client portal can enforce; recorded per sender,
                    // the way the reference's Block Sender is.
                    $ticket->user->properties()->updateOrCreate(
                        ['key' => 'support_blocked'], ['value' => '1'],
                    );
                }
                $ticket->messages()->delete();
                $ticket->delete();
            }
        });

        Notification::make()
            ->title(count($tickets) . ' ticket(s) deleted' . ($block ? ', sender blocked' : ''))
            ->success()->send();
    }

    protected function getViewData(): array
    {
        $tickets = $this->query()->paginate(self::PER_PAGE, page: $this->page);

        if ($this->page > 1 && $tickets->isEmpty()) {
            $this->page = max(1, $tickets->lastPage());
            $tickets = $this->query()->paginate(self::PER_PAGE, page: $this->page);
        }

        return [
            'tickets' => $tickets,
            'departments' => (array) config('settings.ticket_departments'),
            'viewLabel' => self::VIEWS[$this->tab] ?? 'All Active Tickets',
            'statusCounts' => $this->statusCounts(),
        ];
    }

    /** The sidebar's "Awaiting Reply (1)"-style numbers, one query per view label. */
    public function statusCounts(): array
    {
        $base = fn () => Ticket::query();

        return [
            'flagged' => (clone $base())->whereNotNull('assigned_to')->where('status', '!=', 'closed')->count(),
            'active' => (clone $base())->where('status', '!=', 'closed')->count(),
            'open' => (clone $base())->where('status', 'open')->count(),
            'answered' => (clone $base())->where('status', 'replied')->count(),
            'closed' => (clone $base())->where('status', 'closed')->count(),
        ];
    }

    private function query()
    {
        $query = Ticket::query()->with(['user', 'assignedTo', 'messages' => fn ($q) => $q->latest()->limit(1)]);

        $query = match ($this->tab) {
            'flagged' => $query->whereNotNull('assigned_to')->where('status', '!=', 'closed'),
            'open' => $query->where('status', 'open'),
            'answered' => $query->where('status', 'replied'),
            'customer-reply' => $query->where('status', 'open')
                ->whereHas('messages', fn ($q) => $q->whereColumn('ticket_messages.user_id', 'tickets.user_id')
                    ->whereRaw('ticket_messages.id = (select max(id) from ticket_messages tm where tm.ticket_id = tickets.id)')),
            // No such state exists here; an empty list is the honest rendering.
            'on-hold' => $query->whereRaw('1 = 0'),
            'in-progress' => $query->whereNotNull('assigned_to')->where('status', '!=', 'closed'),
            'closed' => $query->where('status', 'closed'),
            default => $query->where('status', '!=', 'closed'),
        };

        if ($this->q !== '') {
            $query->where(fn ($q) => $q->where('subject', 'like', '%' . $this->q . '%')
                ->orWhere('id', (int) $this->q));
        }

        if ($this->dept !== '') {
            $query->where('department', $this->dept);
        }

        if ($this->email !== '') {
            $query->whereHas('user', fn ($q) => $q->where('email', 'like', '%' . $this->email . '%'));
        }

        return $query->latest('updated_at');
    }
}
