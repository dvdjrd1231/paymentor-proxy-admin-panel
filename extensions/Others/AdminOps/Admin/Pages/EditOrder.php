<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OrderResource;
use App\Helpers\NotificationHelper;
use App\Jobs\Server\CreateJob;
use App\Jobs\Server\SuspendJob;
use App\Jobs\Server\UnsuspendJob;
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
     * The reference's per-item provisioning ticks, keyed by service id.
     *
     * Defaulted in mount() rather than left empty, because a box unticked by absence would
     * silently stop provisioning for anyone who accepted an order without touching them.
     *
     * @var array<int, bool>
     */
    public array $runModuleCreate = [];

    /** @var array<int, bool> */
    public array $sendWelcome = [];

    /** The reference's Add Notes — kept in ext_ao_meta, since orders carry no column. */
    public bool $notesOpen = false;

    public string $orderNotes = '';

    public function saveNotes(): void
    {
        abort_unless(OrderResource::canEdit($this->order), 403);

        \Paymenter\Extensions\Others\AdminOps\Models\Meta::put($this->order, 'notes', trim($this->orderNotes));
        $this->notesOpen = false;

        Notification::make()->title('Notes saved')->success()->send();
    }

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
     * The header's Status select: picking a state moves every one of this order's services to
     * it, running the side effects that state implies.
     *
     * Not a straight map onto the buttons below any more. Those are one-way ratchets — Accept
     * only touches what is pending, Set Back to Pending only what is live — so routing the
     * select through them left most transitions as silent no-ops: Suspended did nothing at
     * all, and Cancelled → Active did nothing because no service was pending by then. The
     * reference's select changes the status from wherever it is, so this does too.
     */
    public function setStatus(string $to): void
    {
        match ($to) {
            'active' => $this->acceptOrder(),
            'suspended' => $this->suspendOrder(),
            'pending' => $this->setOrderPending(),
            'cancelled' => $this->cancelOrder(),
            default => null,
        };
    }

    public function mount(int|string $record): void
    {
        $this->order = Order::with(['user', 'services.product'])->findOrFail($record);

        $this->orderNotes = (string) (\Paymenter\Extensions\Others\AdminOps\Models\Meta::for($this->order)['notes'] ?? '');

        // Both provisioning ticks start on, so an order accepted without touching them
        // behaves exactly as it did before they existed.
        foreach ($this->order->services as $service) {
            $this->runModuleCreate[$service->id] = true;
            $this->sendWelcome[$service->id] = true;
        }

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
        $skipped = 0;
        $resumed = 0;
        $gone = 0;

        DB::transaction(function () use (&$count, &$skipped, &$resumed, &$gone): void {
            foreach ($this->order->services as $service) {
                // A cancelled service is gone from its panel, so there is nothing here that
                // could bring it back — counted and reported rather than silently skipped.
                if ($service->status === 'cancelled') {
                    $gone++;

                    continue;
                }

                // Already live: the select re-picking Active is not an instruction to
                // re-provision anything.
                if ($service->status === 'active') {
                    continue;
                }

                if ($service->status === 'suspended') {
                    UnsuspendJob::dispatch($service);
                    $service->update(['status' => 'active']);
                    $resumed++;

                    continue;
                }

                // The reference's two per-item ticks. Accepting used to always provision,
                // which left no way to accept an order you had already set up by hand.
                $create = (bool) ($this->runModuleCreate[$service->id] ?? true);
                $welcome = (bool) ($this->sendWelcome[$service->id] ?? true);

                if ($create && $service->product?->server) {
                    // CreateJob sends the welcome itself once the panel answers.
                    CreateJob::dispatch($service);
                } else {
                    if ($service->product?->server) {
                        $skipped++;
                    }

                    // Nothing is going to run, so the welcome has to come from here or not
                    // at all — which is exactly what the tick decides.
                    if ($welcome) {
                        try {
                            NotificationHelper::serverCreatedNotification($service->user, $service);
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                }

                $service->status = 'active';
                $service->expires_at = $service->calculateNextDueDate();
                $service->save();
                $count++;
            }
        });

        $this->refreshOrder();

        $moved = $count + $resumed;
        $detail = array_filter([
            $count ? $count . ' activated' : null,
            $resumed ? $resumed . ' resumed' : null,
            $skipped ? $skipped . ' not provisioned' : null,
            $gone ? $gone . ' already terminated and cannot be revived' : null,
        ]);

        Notification::make()
            ->title($moved ? 'Accepted: ' . implode(', ', $detail) : 'Nothing on this order to activate')
            ->body($moved || !$gone ? null : 'A terminated service no longer exists on its panel — place a new order instead.')
            ->{$moved ? 'success' : 'warning'}()->send();
    }

    /** The select's Suspended: stop every live service, the way the service editor's own
     *  Suspend command does. */
    public function suspendOrder(): void
    {
        $count = 0;

        DB::transaction(function () use (&$count): void {
            foreach ($this->order->services->where('status', 'active') as $service) {
                SuspendJob::dispatch($service);
                $service->update(['status' => 'suspended']);
                $count++;
            }
        });

        $this->refreshOrder();
        Notification::make()->title($count ? 'Suspended: ' . $count . ' service(s)' : 'Nothing active on this order')
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
        $from = ['active', 'suspended', 'cancelled'];
        $count = $this->order->services->whereIn('status', $from)->count();
        $this->order->services()->whereIn('status', $from)->update(['status' => 'pending']);

        $this->refreshOrder();
        Notification::make()->title($count ? 'Set back to pending: ' . $count . ' service(s)' : 'This order is already pending')
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
            'services.coupon', 'services.properties', 'user.properties',
        ]);

        $properties = $this->order->user?->properties?->pluck('value', 'key') ?? collect();

        // The reference's IP Address and Order Placed By lines, from the created audit —
        // the same trail the list's IP filter searches. The audit's user is whoever was
        // logged in when the order was created: an admin placing it from here, or the
        // client ordering at checkout.
        $created = DB::table('audits')
            ->where('auditable_type', Order::class)
            ->where('auditable_id', $this->order->id)
            ->where('event', 'created')
            ->first(['ip_address', 'user_id']);
        $ip = $created?->ip_address;

        $actor = ($created?->user_id ? \App\Models\User::find($created->user_id) : null) ?? $this->order->user;
        $placedBy = $actor ? [
            'role' => $actor->role_id ? 'Admin' : 'User',
            'name' => trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) ?: $actor->email,
            'id' => $actor->id,
            'email' => $actor->email,
        ] : null;

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
            'placedBy' => $placedBy,
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
