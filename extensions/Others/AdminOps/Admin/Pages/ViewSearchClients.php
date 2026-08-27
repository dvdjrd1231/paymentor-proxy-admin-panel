<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\UserResource;
use App\Models\Service;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's View/Search Clients, to its screenshot: the search band with its round green
 * glass, the "N Records Found" line with Jump to Page and the Hide Inactive toggle, the
 * navy-headed grid, and With Selected underneath.
 *
 * A page and not a themed UserResource, for the documented reason ({@see docs/02b-admin-area.md}):
 * a resource's `table()` builds its own column array and cannot be reshaped from an
 * extension, and this screen's columns are WHMCS's — ID, names, company, email, services,
 * created, status — not core's. Reading is all it does; every row leads to the core screens
 * that own the record, so there is still exactly one place a customer is edited.
 *
 * ## What a "client" is here, and what "Active" means
 *
 * A client is a user with no admin role — the same split the topbar uses. WHMCS stores a
 * status on the client; Paymenter deliberately has no such column, so status is *derived*:
 * a client with at least one service that is pending, active or suspended is Active, and
 * one with nothing but cancelled history (or nothing at all) is Inactive. Derived means it
 * cannot go stale, which a stored flag can.
 *
 * The Hide Inactive Clients toggle starts ON, as the reference ships it.
 */
class ViewSearchClients extends Page
{
    protected string $view = 'adminops::pages.view-search-clients';

    protected static ?string $slug = 'view-clients';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 25;

    /** Every search field is a URL, so a filtered list can be pasted into a ticket. */
    #[Url]
    public string $name = '';

    #[Url]
    public string $email = '';

    #[Url]
    public string $phone = '';

    #[Url]
    public string $status = '';

    #[Url]
    public bool $hideInactive = true;

    #[Url]
    public int $page = 1;

    public static function canAccess(): bool
    {
        return UserResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'View/Search Clients';
    }

    /** The band's Search button. Back to page 1: a new search on page 3 of the old one is nothing. */
    public function search(): void
    {
        $this->page = 1;
    }

    public function toggleInactive(): void
    {
        $this->hideInactive = !$this->hideInactive;
        $this->page = 1;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    protected function getViewData(): array
    {
        $clients = $this->query()->paginate(self::PER_PAGE, page: $this->page);

        // A page number beyond the end — a search narrowed under the current page — shows
        // the last real page rather than an empty grid claiming no records exist.
        if ($this->page > 1 && $clients->isEmpty()) {
            $this->page = max(1, $clients->lastPage());
            $clients = $this->query()->paginate(self::PER_PAGE, page: $this->page);
        }

        return [
            'clients' => $clients,
            'hiddenCount' => $this->hideInactive ? $this->withFilters(false)->count() - $this->withFilters(true)->count() : 0,
        ];
    }

    private function query()
    {
        return $this->withFilters($this->hideInactive)
            ->withCount(['services as services_count' => fn ($q) => $q->whereIn('status', self::OPEN)])
            ->with(['properties' => fn ($q) => $q->whereIn('key', ['company_name', 'company'])])
            ->orderByDesc('id');
    }

    /** Service states that count as "still theirs" — everything but cancelled. */
    private const OPEN = [Service::STATUS_PENDING, Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED];

    private function withFilters(bool $hideInactive)
    {
        $query = User::query()->whereNull('role_id');

        if ($this->name !== '') {
            $query->where(function ($q): void {
                $q->where('first_name', 'like', '%' . $this->name . '%')
                    ->orWhere('last_name', 'like', '%' . $this->name . '%')
                    ->orWhereHas('properties', fn ($p) => $p
                        ->whereIn('key', ['company_name', 'company'])
                        ->where('value', 'like', '%' . $this->name . '%'));
            });
        }

        if ($this->email !== '') {
            $query->where('email', 'like', '%' . $this->email . '%');
        }

        if ($this->phone !== '') {
            $query->whereHas('properties', fn ($p) => $p
                ->where('key', 'like', '%phone%')
                ->where('value', 'like', '%' . $this->phone . '%'));
        }

        $active = fn ($q) => $q->whereHas('services', fn ($s) => $s->whereIn('status', self::OPEN));

        if ($this->status === 'active' || $hideInactive && $this->status === '') {
            $active($query);
        } elseif ($this->status === 'inactive') {
            $query->whereDoesntHave('services', fn ($s) => $s->whereIn('status', self::OPEN));
        }

        return $query;
    }

    /** Whether this client counts as Active — same rule the query filters by. */
    public static function isActive(User $client): bool
    {
        return $client->services_count > 0;
    }

    /**
     * @return array<int, int> The page numbers the Jump to Page select offers.
     */
    public static function pages(LengthAwarePaginator $paginator): array
    {
        return range(1, max(1, $paginator->lastPage()));
    }
}
