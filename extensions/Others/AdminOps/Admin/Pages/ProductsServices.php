<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ServiceResource;
use App\Models\Category;
use App\Models\Server;
use App\Models\Service;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's List All Products/Services, to its screenshot: the Search/Filter tab over the
 * band, the records line with Jump to Page and Hide Inactive, and the navy grid — ID,
 * Product/Service, Domain, Client Name, Price, Billing Cycle, Next Due Date, Status.
 *
 * A page, not a themed ServiceResource — the documented reason as everywhere in this
 * family: a resource's table cannot be reshaped from an extension, and these columns are
 * the reference's. Read-only; every row leads to core's service screen.
 *
 * Domain is always "(No Domain)": domains are removed from this store (brief §10), and the
 * reference renders exactly that for a domainless service, so the column reads the same.
 */
class ProductsServices extends Page
{
    protected string $view = 'adminops::pages.products-services';

    protected static ?string $slug = 'products-services';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 100;

    /** The states that count as live — everything but cancelled. */
    public const OPEN = [Service::STATUS_PENDING, Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED];

    #[Url]
    public bool $filter = false;

    /** The rail's "- Category" sub-entries land here, as the reference filters by group. */
    #[Url]
    public string $category = '';

    #[Url]
    public string $product = '';

    #[Url]
    public string $client = '';

    #[Url]
    public string $status = '';

    /**
     * Issue #4 — three of the reference's Search/Filter fields the band never exposed:
     * Product Type is the category filter the rail's own links already set via URL, given a
     * visible control here for the first time; Billing Cycle and Server are new. Payment
     * Method and the Custom Field pair are the reference's own but left out — a service
     * carries no gateway of its own to filter by (that lives on its invoices), and Paymenter
     * has no per-product custom fields to search, so both would be a control that always
     * returns nothing. Domain is the same story and already documented above.
     */
    #[Url]
    public string $cycle = '';

    #[Url]
    public string $server = '';

    #[Url]
    public bool $hideInactive = true;

    #[Url]
    public int $page = 1;

    public static function canAccess(): bool
    {
        return ServiceResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Products/Services';
    }

    /** Issue #4 — the reference's "+" opens the row into an inline detail strip. */
    public ?int $expanded = null;

    public function expand(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

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
        $services = $this->query()->paginate(self::PER_PAGE, page: $this->page);

        if ($this->page > 1 && $services->isEmpty()) {
            $this->page = max(1, $services->lastPage());
            $services = $this->query()->paginate(self::PER_PAGE, page: $this->page);
        }

        return [
            'services' => $services,
            'hiddenCount' => $this->hideInactive
                ? $this->filtered(false)->count() - $this->filtered(true)->count()
                : 0,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'servers' => Server::orderBy('name')->get(['id', 'name']),
            // Batched once for the whole page rather than per row — the same tie the
            // client's own service list reads, {@see themes/proxy/views/services/index.blade.php}.
            'addonParents' => \Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon::whereIn('service_id', $services->pluck('id'))
                ->with('parent.product')->get()->keyBy('service_id'),
        ];
    }

    private function query()
    {
        return $this->filtered($this->hideInactive)
            ->with(['product', 'user', 'plan'])
            ->orderByDesc('id');
    }

    private function filtered(bool $hideInactive)
    {
        $query = Service::query();

        if ($this->product !== '') {
            $query->whereHas('product', fn ($q) => $q->where('name', 'like', '%' . $this->product . '%'));
        }

        if ($this->category !== '' && ctype_digit($this->category)) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', (int) $this->category));
        }

        if ($this->client !== '') {
            $query->whereHas('user', fn ($q) => $q
                ->where('first_name', 'like', '%' . $this->client . '%')
                ->orWhere('last_name', 'like', '%' . $this->client . '%')
                ->orWhere('email', 'like', '%' . $this->client . '%'));
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        } elseif ($hideInactive) {
            $query->whereIn('status', self::OPEN);
        }

        if ($this->server !== '' && ctype_digit($this->server)) {
            $query->whereHas('product', fn ($q) => $q->where('server_id', (int) $this->server));
        }

        if ($this->cycle !== '') {
            // A billing cycle is derived (unit + period + type), not a column — filtering by
            // it means asking the same question cycle() answers, for every plan a service
            // could have, and keeping the ones that match. Small tables (plans, not
            // services), so this stays a handful of queries rather than one enormous join.
            $matching = \App\Models\Plan::query()
                ->get(['id', 'type', 'billing_unit', 'billing_period'])
                ->filter(fn ($plan) => \Paymenter\Extensions\Others\AdminOps\Support\ProductConfig::cycleLabel($plan) === $this->cycle)
                ->pluck('id');
            $query->whereIn('plan_id', $matching);
        }

        return $query;
    }

    /** The reference's label for each state — cancelled reads TERMINATED there. */
    public static function statusLabel(string $status): string
    {
        return $status === Service::STATUS_CANCELLED ? 'Terminated' : ucfirst($status);
    }

    /**
     * One Time / Monthly / Weekly…, as the reference words a billing cycle. The match itself
     * lives in {@see \Paymenter\Extensions\Others\AdminOps\Support\ProductConfig::cycleLabel()},
     * which Add New Order also needs before a service exists to label — this keeps the one
     * copy of the wording.
     */
    public static function cycle(Service $service): string
    {
        return \Paymenter\Extensions\Others\AdminOps\Support\ProductConfig::cycleLabel($service->plan);
    }
}
