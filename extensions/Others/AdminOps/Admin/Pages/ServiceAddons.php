<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ServiceResource;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #7 — WHMCS's Service Addons: an add-on linked to an active service (an extra IP,
 * more bandwidth), with its own recurring price.
 *
 * ## How it works here
 *
 * An addon **is a service row** — that single decision buys the whole billing lifecycle:
 * core raises its renewal invoices, suspends it when unpaid, and the admin's normal
 * service tools all apply. What core cannot hold is whose addon it is; that link lives in
 * `ext_service_addons`, written when the addon is attached.
 *
 * The addon catalogue is a product category named **Service Addons**: staff define addons
 * as ordinary products there (name, monthly price), which keeps pricing where every other
 * price already lives. Attaching aligns the addon's next due date with its parent, so one
 * renewal invoice carries both — WHMCS's own behaviour.
 */
class ServiceAddons extends Page
{
    protected string $view = 'adminops::pages.service-addons';

    protected static ?string $slug = 'service-addons';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const CATEGORY = 'Service Addons';

    #[Url]
    public string $q = '';

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

    public function toggleAdding(): void
    {
        $this->adding = !$this->adding;
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
        $this->reset(['parentId', 'productId', 'quantity', 'price']);
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

        $addons = ServiceAddon::with(['service.product', 'parent.product', 'parent.user'])
            ->latest('id')->limit(200)->get()
            ->filter(fn ($a) => $a->service && $a->parent)
            ->when($this->q !== '', fn ($list) => $list->filter(fn ($a) => str_contains(
                strtolower(($a->parent->user->email ?? '') . ' ' . ($a->service->product->name ?? '')),
                strtolower($this->q),
            )));

        return [
            'addons' => $addons,
            'catalogue' => Product::where('category_id', $category->id)->orderBy('name')->get(['id', 'name']),
            'parents' => Service::whereIn('status', ['active', 'suspended'])
                ->where(fn ($q) => $q->whereNotIn('id', ServiceAddon::pluck('service_id')))
                ->with(['product', 'user'])->latest('id')->limit(300)->get(),
            'categoryEmpty' => !Product::where('category_id', $category->id)->exists(),
        ];
    }
}
