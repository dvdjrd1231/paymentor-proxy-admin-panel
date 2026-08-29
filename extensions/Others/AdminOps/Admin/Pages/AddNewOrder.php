<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OrderResource;
use App\Models\Coupon;
use App\Models\Gateway;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Add New Order, to its screenshot: client, payment method, promotion code, order
 * status, a Product/Service block **per line** with the reference's "+ Add Another
 * Product", and the Order Summary card with Submit Order.
 *
 * The creation path is a copy of what checkout does ({@see \App\Livewire\Cart::checkout()}):
 * an Order row, a pending Service per line, and — when Generate Invoice is ticked — one
 * Invoice with an item per service, due in seven days. Same tables, same shapes, so
 * everything downstream (cron, provisioning, the client area) treats an admin-placed order
 * exactly like a customer-placed one.
 */
class AddNewOrder extends Page
{
    protected string $view = 'adminops::pages.add-new-order';

    protected static ?string $slug = 'add-order';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?int $userId = null;

    public ?int $gatewayId = null;

    public ?int $couponId = null;

    /**
     * The reference's product lines: one row per "+ Add Another Product".
     *
     * @var array<int, array{productId: int|string|null, planId: int|string|null, quantity: int|string, priceOverride: string, domain: string}>
     */
    public array $items = [];

    public bool $generateInvoice = true;

    public bool $sendEmail = true;

    public static function canAccess(): bool
    {
        return OrderResource::canCreate();
    }

    public function getTitle(): string
    {
        return 'Add New Order';
    }

    public function mount(): void
    {
        $this->items = [self::blankItem()];
    }

    private static function blankItem(): array
    {
        return ['productId' => null, 'planId' => null, 'quantity' => 1, 'priceOverride' => '', 'domain' => ''];
    }

    /** The reference's "+ Add Another Product". */
    public function addItem(): void
    {
        $this->items[] = self::blankItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items) ?: [self::blankItem()];
    }

    /** A product pick defaults its line to the product's first plan. */
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

    /** The live Order Summary: a line per picked product, unit price or override. */
    public function summary(): array
    {
        $currency = config('settings.default_currency', 'USD');
        $products = Product::whereIn('id', collect($this->items)->pluck('productId')->filter())->get()->keyBy('id');

        $lines = [];
        foreach ($this->items as $index => $item) {
            if (!$item['productId']) {
                continue;
            }

            $plan = $item['planId'] ? Plan::with('prices')->find($item['planId']) : null;
            $unit = $item['priceOverride'] !== '' && is_numeric($item['priceOverride'])
                ? (float) $item['priceOverride']
                : (float) ($plan?->price($currency)->price ?? 0);
            $quantity = max(1, (int) $item['quantity']);

            $lines[] = [
                'index' => $index,
                'label' => $products[$item['productId']]?->name ?? '—',
                'plan' => $plan,
                'unit' => $unit,
                'quantity' => $quantity,
                'total' => $unit * $quantity,
                'domain' => trim($item['domain']),
            ];
        }

        return [
            'currency' => $currency,
            'lines' => $lines,
            'total' => array_sum(array_column($lines, 'total')),
        ];
    }

    public function create(): void
    {
        $this->validate([
            'userId' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.priceOverride' => 'nullable|numeric|min:0',
        ], attributes: ['userId' => 'client']);

        $summary = $this->summary();

        if ($summary['lines'] === []) {
            $this->addError('items', 'Pick at least one product.');

            return;
        }

        foreach ($summary['lines'] as $line) {
            if (!$line['plan']) {
                $this->addError('items', 'Every product line needs a billing cycle.');

                return;
            }
        }

        $user = User::whereNull('role_id')->findOrFail($this->userId);

        $order = DB::transaction(function () use ($user, $summary): Order {
            $order = new Order([
                'user_id' => $user->id,
                'currency_code' => $summary['currency'],
            ]);
            // The reference's Send Email checkbox — the observer sends the order
            // confirmation unless told not to.
            $order->send_create_email = $this->sendEmail;
            $order->save();

            $invoice = null;
            if ($this->generateInvoice && $summary['total'] > 0) {
                $invoice = new Invoice([
                    'user_id' => $user->id,
                    'due_at' => now()->addDays(7),
                    'currency_code' => $summary['currency'],
                ]);
                $invoice->save();
            }

            foreach ($summary['lines'] as $line) {
                $service = $order->services()->create([
                    'user_id' => $user->id,
                    'currency_code' => $summary['currency'],
                    'product_id' => $this->items[$line['index']]['productId'],
                    'plan_id' => $line['plan']->id,
                    'price' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'coupon_id' => $this->couponId,
                    'status' => Service::STATUS_PENDING,
                ]);

                if ($line['domain'] !== '') {
                    $service->properties()->updateOrCreate(['key' => 'domain'], ['name' => 'Domain', 'value' => $line['domain']]);
                }

                $invoice?->items()->create([
                    'reference_id' => $service->id,
                    'reference_type' => Service::class,
                    'price' => $line['total'],
                    'quantity' => $line['quantity'],
                    'description' => $service->description,
                ]);
            }

            return $order;
        });

        Notification::make()->title('Order #' . $order->id . ' placed')
            ->body(count($summary['lines']) . ' product(s) for ' . $user->name)
            ->success()->send();

        $this->redirect(ManageOrders::getUrl(['status' => 'pending']));
    }

    protected function getViewData(): array
    {
        return [
            'clients' => User::whereNull('role_id')->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'email']),
            'gateways' => Gateway::where('enabled', true)->get(['id', 'name']),
            'coupons' => Coupon::query()->orderBy('code')->limit(100)->get(['id', 'code']),
            'products' => Product::with('category')->orderBy('name')->get(['id', 'name', 'category_id']),
            'plansByItem' => collect($this->items)->map(fn ($item) => $this->plansFor($item['productId'])),
            'summary' => $this->summary(),
        ];
    }
}
