<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ServiceResource;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #7 — WHMCS's Service Addons, now to its screenshot: the Search/Filter framed
 * panel (addon, product/service, status, client name, server, billing cycle), the navy
 * grid with the reference's columns, With Selected: Send Message, and Hide Inactive.
 *
 * An addon **is a service row** — that single decision buys the whole billing lifecycle:
 * core raises its renewal invoices, suspends it when unpaid, and the admin's normal
 * service tools all apply. What core cannot hold is whose addon it is; that link lives in
 * `ext_service_addons`. The catalogue is the **Service Addons** product category.
 */
class ServiceAddons extends Page
{
    protected string $view = 'adminops::pages.service-addons';

    protected static ?string $slug = 'service-addons';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const CATEGORY = 'Service Addons';

    public bool $filter = false;

    #[Url]
    public string $addon = '';

    #[Url]
    public string $parentProduct = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $clientName = '';

    #[Url]
    public string $server = '';

    #[Url]
    public string $cycle = '';

    #[Url]
    public bool $hideInactive = true;

    /** The "Add Addon" form. */
    public bool $adding = false;

    public ?int $parentId = null;

    public ?int $productId = null;

    public int $quantity = 1;

    public string $price = '';

    public ?string $confirmingCancel = null;

    public static function canAccess(): bool
    {
        return ServiceResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Service Addons';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function toggleAdding(): void
    {
        $this->adding = !$this->adding;
    }

    public function toggleInactive(): void
    {
        $this->hideInactive = !$this->hideInactive;
    }

    /** Prefill the price from the picked addon product's plan when the admin has not typed one. */
    public function updatedProductId(): void
    {
        if (!$this->productId) {
            return;
        }

        $plan = Plan::where('priceable_type', Product::class)->where('priceable_id', $this->productId)->first();
        $currency = config('settings.default_currency', 'USD');
        $this->price = number_format((float) ($plan?->price($currency)->price ?? 0), 2, '.', '');
    }

    public function attach(): void
    {
        $this->validate([
            'parentId' => 'required|exists:services,id',
            'productId' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ], attributes: ['parentId' => 'service', 'productId' => 'addon']);

        $parent = Service::with('order')->findOrFail($this->parentId);
        $product = Product::findOrFail($this->productId);
        $plan = Plan::where('priceable_type', Product::class)->where('priceable_id', $product->id)->first();

        DB::transaction(function () use ($parent, $product, $plan): void {
            $service = Service::create([
                'order_id' => $parent->order_id,
                'product_id' => $product->id,
                'plan_id' => $plan?->id,
                'quantity' => $this->quantity,
                'price' => (float) $this->price,
                'status' => 'active',
                'user_id' => $parent->user_id,
                'currency_code' => $parent->currency_code,
                // Due with its parent, so one renewal invoice carries both — the
                // reference's own behaviour for addons.
                'expires_at' => $parent->expires_at,
            ]);

            ServiceAddon::create(['service_id' => $service->id, 'parent_service_id' => $parent->id]);
        });

        $this->adding = false;
        $this->reset(['parentId', 'productId', 'price']);
        $this->quantity = 1;
        Notification::make()->title('Addon attached')
            ->body('It renews with its parent service on the same invoice.')->success()->send();
    }

    public function cancel(): void
    {
        if (!$this->confirmingCancel) {
            return;
        }

        $addon = ServiceAddon::with('service')->findOrFail((int) $this->confirmingCancel);
        $this->confirmingCancel = null;
        $addon->service?->update(['status' => 'cancelled']);
        Notification::make()->title('Addon cancelled')->success()->send();
    }

    protected function getViewData(): array
    {
        // The catalogue category, made once; staff add addon products to it normally.
        $category = Category::firstOrCreate(['name' => self::CATEGORY], ['sort' => 99, 'slug' => 'service-addons']);

        $addons = ServiceAddon::with(['service.product', 'service.plan', 'parent.product', 'parent.user'])
            ->latest('id')->limit(300)->get()
            ->filter(fn ($a) => $a->service && $a->parent);

        $hiddenCount = $addons->filter(fn ($a) => $a->service->status === 'cancelled')->count();

        $addons = $addons
            ->when($this->hideInactive, fn ($list) => $list->filter(fn ($a) => $a->service->status !== 'cancelled'))
            ->when($this->addon !== '', fn ($list) => $list->filter(fn ($a) => (string) $a->service->product_id === $this->addon))
            ->when($this->parentProduct !== '', fn ($list) => $list->filter(fn ($a) => (string) $a->parent->product_id === $this->parentProduct))
            ->when($this->status !== '', fn ($list) => $list->filter(fn ($a) => $a->service->status === $this->status))
            ->when($this->server !== '', fn ($list) => $list->filter(fn ($a) => (string) ($a->parent->product?->server_id ?? '') === $this->server))
            ->when($this->cycle !== '', fn ($list) => $list->filter(fn ($a) => ProductsServices::cycle($a->service) === $this->cycle))
            ->when($this->clientName !== '', fn ($list) => $list->filter(fn ($a) => str_contains(
                strtolower(($a->parent->user->first_name ?? '') . ' ' . ($a->parent->user->last_name ?? '') . ' ' . ($a->parent->user->email ?? '')),
                strtolower($this->clientName),
            )))
            ->values();

        return [
            'addons' => $addons,
            'hiddenCount' => $hiddenCount,
            'catalogue' => Product::where('category_id', $category->id)->orderBy('name')->get(['id', 'name']),
            'parentProducts' => Product::where('category_id', '!=', $category->id)->orderBy('name')->get(['id', 'name']),
            'servers' => Server::orderBy('name')->get(['id', 'name']),
            'parents' => Service::whereIn('status', ['active', 'suspended'])
                ->where(fn ($q) => $q->whereNotIn('id', ServiceAddon::pluck('service_id')))
                ->with(['product', 'user'])->latest('id')->limit(300)->get(),
            'categoryEmpty' => !Product::where('category_id', $category->id)->exists(),
        ];
    }
}
