<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ServiceResource;
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

    #[Url]
    public string $product = '';

    #[Url]
    public string $client = '';

    #[Url]
    public string $status = '';

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

        return $query;
    }

    /** The reference's label for each state — cancelled reads TERMINATED there. */
    public static function statusLabel(string $status): string
    {
        return $status === Service::STATUS_CANCELLED ? 'Terminated' : ucfirst($status);
    }

    /** One Time / Monthly / Weekly…, as the reference words a billing cycle. */
    public static function cycle(Service $service): string
    {
        $plan = $service->plan;

        if (!$plan || $plan->type === 'one-time') {
            return 'One Time';
        }

        if ($plan->type === 'free') {
            return 'Free';
        }

        return match ($plan->billing_unit) {
            'day' => $plan->billing_period == 1 ? 'Daily' : $plan->billing_period . ' Days',
            'week' => $plan->billing_period == 1 ? 'Weekly' : $plan->billing_period . ' Weeks',
            'month' => $plan->billing_period == 1 ? 'Monthly' : $plan->billing_period . ' Months',
            'year' => $plan->billing_period == 1 ? 'Annually' : $plan->billing_period . ' Years',
            default => ucfirst((string) $plan->type),
        };
    }
}
