<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OrderResource;
use App\Jobs\Server\CreateJob;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Edit Order, in Add New Order's clothes — Leandro's point: the two screens are the same
 * form, one empty and one filled. Client and date at the top, a product line per service
 * (product, billing cycle, quantity, price, status), lines addable and removable, Save.
 *
 * Removing a line only removes services that never ran (pending or cancelled) — a
 * provisioned service is cancelled from its own page, not silently deleted here. Setting a
 * pending line to Active provisions it exactly the way checkout's zero-total path does.
 */
class EditOrder extends Page
{
    protected string $view = 'adminops::pages.edit-order';

    protected static ?string $slug = 'edit-order';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Same reasoning as {@see ClientSummary::$customer} — not `$record`. */
    public Order $order;

    /**
     * @var array<int, array{id: int|null, productId: int|string|null, planId: int|string|null, quantity: int|string, price: string, status: string}>
     */
    public array $items = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public static function canAccess(): bool
    {
        return OrderResource::canViewAny();
    }

    public function getTitle(): string
    {
        // The reference keeps the list's own heading on its order view.
        return 'Manage Orders';
    }

    /**
     * The header's Status select: picking a state runs the matching whole-order action —
     * the same three the buttons below wire, reached the reference's way.
     */
    public function setStatus(string $to): void
    {
        match ($to) {
            'active' => $this->acceptOrder(),
            'pending' => $this->setOrderPending(),
            'cancelled' => $this->cancelOrder(),
            default => null,
        };
    }

    public function mount(int|string $record): void
    {
        $this->order = Order::with(['user', 'services.product'])->findOrFail($record);

        $this->items = $this->order->services->map(fn (Service $service): array => [
            'id' => $service->id,
            'productId' => $service->product_id,
            'planId' => $service->plan_id,
            'quantity' => $service->quantity,
            'price' => number_format((float) $service->price, 2, '.', ''),
            'status' => $service->status,
        ])->values()->all();

        if ($this->items === []) {
            $this->items = [self::blankItem()];
        }
    }

    private static function blankItem(): array
    {
        return ['id' => null, 'productId' => null, 'planId' => null, 'quantity' => 1, 'price' => '', 'status' => 'pending'];
    }

    public function addItem(): void
    {
        $this->items[] = self::blankItem();
    }

    public function removeItem(int $index): void
    {
        $item = $this->items[$index] ?? null;

        if ($item && $item['id']) {
            $service = Service::find($item['id']);

            if ($service && !in_array($service->status, ['pending', 'cancelled'], true)) {
                Notification::make()->title('Cannot remove')
                    ->body('This service is ' . $service->status . ' — cancel it from its own page first.')
                    ->danger()->send();

                return;
            }

            $service?->delete();
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items) ?: [self::blankItem()];
    }

    /** A product pick defaults its line to the product's first plan, as Add New Order does. */
    public function updatedItems($value, ?string $key = null): void
    {
        if ($key === null || !str_ends_with($key, '.productId')) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $this->items[$index]['planId'] = $this->plansFor($value)->first()?->id;
    }

    public function plansFor($productId)
    {
        return $productId
            ? Plan::where('priceable_type', Product::class)->where('priceable_id', $productId)->get()
            : collect();
    }

    /**
     * The reference's row of whole-order buttons. Four of its five: Set as Fraud has no
     * home here because Paymenter's Service has no fraud status to set — core defines
     * pending/active/suspended/cancelled and nothing else, and ManageOrders' own "Fraud
     * Orders" filter already says so honestly by matching nothing rather than pretending.
     *
     * Each acts on every one of this order's own services at once — the fast path for what
     * the per-line Status dropdowns above already let you do one at a time.
     */
    public function acceptOrder(): void
    {
        $count = 0;

        DB::transaction(function () use (&$count): void {
            foreach ($this->order->services->where('status', 'pending') as $service) {
                if ($service->product?->server) {
                    CreateJob::dispatch($service);
                }

                $service->status = 'active';
                $service->expires_at = $service->calculateNextDueDate();
                $service->save();
                $count++;
            }
        });

        $this->refreshOrder();
        Notification::make()->title($count ? 'Accepted: ' . $count . ' service(s) activated' : 'Nothing pending on this order')
            ->{$count ? 'success' : 'warning'}()->send();
    }

    public function cancelOrder(): void
    {
        $count = $this->order->services->whereIn('status', ['pending', 'active', 'suspended'])->count();
        $this->order->services()->whereIn('status', ['pending', 'active', 'suspended'])->update(['status' => 'cancelled']);

        $this->refreshOrder();
        Notification::make()->title($count ? 'Cancelled: ' . $count . ' service(s)' : 'Nothing running on this order')
            ->{$count ? 'success' : 'warning'}()->send();
    }

    /**
     * A metadata correction, not a deprovision: for reversing an order accepted by mistake
     * before anything downstream depended on it being active. A service already delivered
     * stays running on its panel regardless of what this column says — cancel it properly
     * instead if the service itself needs to stop.
     */
    public function setOrderPending(): void
    {
        $count = $this->order->services->whereIn('status', ['active', 'suspended'])->count();
        $this->order->services()->whereIn('status', ['active', 'suspended'])->update(['status' => 'pending']);

        $this->refreshOrder();
        Notification::make()->title($count ? 'Set back to pending: ' . $count . ' service(s)' : 'Nothing active or suspended on this order')
            ->{$count ? 'success' : 'warning'}()->send();
    }

    /** Same guard as {@see ManageOrders::deleteSelected()}: a running order is not paperwork. */
    public function deleteOrder(): void
    {
        if ($this->order->services->whereIn('status', ['active', 'suspended'])->isNotEmpty()) {
            Notification::make()->title('Cannot delete')
                ->body('This order has active or suspended services — cancel them first.')
                ->danger()->send();

            return;
        }

        $orderId = $this->order->id;
        $this->order->services()->delete();
        $this->order->delete();

        Notification::make()->title('Order #' . $orderId . ' deleted')->success()->send();
        $this->redirect(ManageOrders::getUrl());
    }

    private function refreshOrder(): void
    {
        $this->order->refresh()->load(['user', 'services.product']);
        $this->mount($this->order->id);
    }

    public function save(): void
    {
        $this->validate([
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.status' => 'required|in:pending,active,suspended,cancelled',
        ]);

        $activated = [];

        DB::transaction(function () use (&$activated): void {
            foreach ($this->items as $item) {
                if (!$item['productId'] || !$item['planId']) {
                    continue;
                }

                $service = $item['id'] ? Service::find($item['id']) : null;
                $wasPending = $service?->status === 'pending';

                $values = [
                    'product_id' => $item['productId'],
                    'plan_id' => $item['planId'],
                    'quantity' => max(1, (int) $item['quantity']),
                    'price' => (float) $item['price'],
                    'status' => $item['status'],
                ];

                if ($service) {
                    $service->update($values);
                } else {
                    $service = $this->order->services()->create($values + [
                        'user_id' => $this->order->user_id,
                        'currency_code' => $this->order->currency_code,
                    ]);
                    $wasPending = true;
                }

                // Pending → Active provisions, the way checkout's zero-total path does:
                // dispatch the create job, then stamp the first due date.
                if ($wasPending && $item['status'] === 'active') {
                    if ($service->product->server) {
                        CreateJob::dispatch($service);
                    }

                    $service->expires_at = $service->calculateNextDueDate();
                    $service->save();
                    $activated[] = $service->id;
                }
            }
        });

        $this->order->refresh()->load('services.product');
        $this->mount($this->order->id);

        Notification::make()->title('Order saved')
            ->body($activated !== [] ? 'Activated ' . count($activated) . ' service(s).' : null)
            ->success()->send();
    }

    protected function getViewData(): array
    {
        $this->order->loadMissing([
            'services.product.category', 'services.plan', 'services.invoices.transactions.gateway',
            'services.coupon', 'user.properties',
        ]);

        $properties = $this->order->user?->properties?->pluck('value', 'key') ?? collect();

        // The reference's IP Address line, from the created audit — the same trail the
        // list's IP filter searches.
        $ip = DB::table('audits')
            ->where('auditable_type', Order::class)
            ->where('auditable_id', $this->order->id)
            ->where('event', 'created')
            ->value('ip_address');

        $affiliate = null;

        if (class_exists(\Paymenter\Extensions\Others\Affiliates\Models\AffiliateOrder::class)) {
            $affiliate = \Paymenter\Extensions\Others\Affiliates\Models\AffiliateOrder::with('affiliate.user')
                ->where('order_id', $this->order->id)->first()?->affiliate?->user?->name;
        }

        $invoice = $this->order->services->flatMap->invoices->unique('id')->sortBy('id')->first();

        return [
            'products' => Product::with('category')->orderBy('name')->get(['id', 'name', 'category_id']),
            'plansByItem' => collect($this->items)->map(fn ($item) => $this->plansFor($item['productId'])),
            'payment' => ManageOrders::paymentOf($this->order),
            'statusNow' => ManageOrders::statusOf($this->order),
            'number' => ManageOrders::numberOf($this->order),
            'invoice' => $invoice,
            'ip' => $ip,
            'coupon' => $this->order->services->pluck('coupon')->filter()->first()?->code,
            'affiliateName' => $affiliate,
            'addressLines' => array_values(array_filter([
                trim((string) ($properties['address1'] ?? $properties['address'] ?? '')),
                trim(implode(', ', array_filter([
                    $properties['city'] ?? null, $properties['state'] ?? null, $properties['zip'] ?? $properties['postcode'] ?? null,
                ]))),
                trim((string) ($properties['country'] ?? '')),
            ], fn (string $line): bool => $line !== '')),
        ];
    }

    /** The Payment Status cell per line — this service's own invoices, settled or not. */
    public static function linePayment(Service $service): array
    {
        $invoices = $service->invoices->unique('id');

        if ($invoices->isEmpty()) {
            return ['—', ''];
        }

        return $invoices->every(fn ($invoice) => $invoice->status === 'paid')
            ? ['Complete', 'ao-mo-complete']
            : ['Incomplete', 'ao-mo-incomplete'];
    }
}
