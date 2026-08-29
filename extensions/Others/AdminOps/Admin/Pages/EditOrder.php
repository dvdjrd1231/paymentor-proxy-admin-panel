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
        return 'Edit Order #' . $this->order->id;
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
        return [
            'products' => Product::with('category')->orderBy('name')->get(['id', 'name', 'category_id']),
            'plansByItem' => collect($this->items)->map(fn ($item) => $this->plansFor($item['productId'])),
        ];
    }
}
