<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\Server;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Models\NetworkIssue;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Network Issues, to its screenshots: the Create New Issue form — Title, Type,
 * Server, Priority, Status, Start/End Date, Description — with the reference's own
 * validation banner ("For a server affecting issue, you must select a server."), and the
 * Open / Scheduled / Resolved lists the sidebar links to.
 */
class NetworkIssues extends Page
{
    protected string $view = 'adminops::pages.network-issues';

    protected static ?string $slug = 'network-issues';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** open | scheduled | resolved | all — the list showing; create/edit opens over it. */
    #[Url(as: 'view')]
    public string $tab = 'open';

    #[Url]
    public ?int $editing = null;

    #[Url]
    public bool $creating = false;

    public string $headline = '';   // $title is Filament's

    public string $type = 'server';

    public ?int $server = null;

    public string $priority = 'medium';

    public string $status = 'investigating';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $description = '';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.tickets.viewAny');
    }

    public function getTitle(): string
    {
        return 'Network Issues';
    }

    public function mount(): void
    {
        if ($this->creating || $this->editing) {
            $this->openForm($this->editing);
        }
    }

    public function openForm(?int $id = null): void
    {
        $this->creating = true;
        $this->editing = $id;

        $row = $id ? NetworkIssue::findOrFail($id) : null;
        $this->headline = $row->title ?? '';
        $this->type = $row->type ?? 'server';
        $this->server = $row?->server_id;
        $this->priority = $row->priority ?? 'medium';
        $this->status = $row->status ?? 'investigating';
        $this->startsAt = ($row?->starts_at ?? now())->format('Y-m-d\TH:i');
        $this->endsAt = $row?->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->description = $row->description ?? '';
    }

    public function backToList(): void
    {
        $this->creating = false;
        $this->editing = null;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate([
            'headline' => 'required|string|max:255',
            'type' => 'in:' . implode(',', array_keys(NetworkIssue::TYPES)),
            'priority' => 'in:' . implode(',', array_keys(NetworkIssue::PRIORITIES)),
            'status' => 'in:' . implode(',', array_keys(NetworkIssue::STATUSES)),
            'startsAt' => 'required|date',
            'endsAt' => 'nullable|date',
            'description' => 'required|string',
        ]);

        // The reference's own rule, in its own words.
        if ($this->type === 'server' && !$this->server) {
            $this->addError('server', 'For a server affecting issue, you must select a server.');

            return;
        }

        $values = [
            'title' => $this->headline,
            'type' => $this->type,
            'server_id' => $this->type === 'server' ? $this->server : null,
            'priority' => $this->priority,
            'status' => $this->status,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt ?: null,
            'description' => $this->description,
        ];

        $this->editing
            ? NetworkIssue::findOrFail($this->editing)->update($values)
            : NetworkIssue::create($values);

        Notification::make()->title('Network issue saved')->success()->send();
        $this->backToList();
    }

    public function delete(int $id): void
    {
        NetworkIssue::findOrFail($id)->delete();
        Notification::make()->title('Network issue deleted')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'rows' => NetworkIssue::with('server')->view($this->tab)->orderByDesc('starts_at')->limit(200)->get(),
            'servers' => Server::orderBy('name')->get(['id', 'name']),
            'viewLabel' => ['open' => 'Open', 'scheduled' => 'Scheduled', 'resolved' => 'Resolved'][$this->tab] ?? 'All',
        ];
    }
}
