<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OrderResource;
use App\Helpers\ExtensionHelper;
use App\Jobs\Server\CreateJob;
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
use Paymenter\Extensions\Others\AdminOps\Support\ProductConfig;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Add New Order, to its screenshot: client, payment method, promotion code, order
 * status, a Product/Service block **per line** — with the reference's "+ Add Another
 * Product", its Configurable Options (including a server's own checkout fields, such as
 * ProxyPanel's Region picker), and the Order Summary card with Submit Order.
 *
 * The creation path is a copy of what checkout does ({@see \App\Livewire\Cart::checkout()}):
 * an Order row, a pending Service per line, and — when Generate Invoice is ticked — one
 * Invoice with an item per service, due in seven days. Same tables, same shapes, so
 * everything downstream (cron, provisioning, the client area) treats an admin-placed order
 * exactly like a customer-placed one.
 *
 * Configurable Options are not a ProxyPanel feature — they are core's own `ConfigOption`
 * model (admin-managed under Configuration → Configurable Options, WHMCS's "Configurable
 * Options" by another name), plus whatever a line's server module adds through
 * `getCheckoutConfig()`. ProxyPanel's Region select is one instance of the latter, not a
 * special case here: {@see Support\ProductConfig} calls both through the same
 * `ExtensionHelper` core's own checkout page uses, so a line offers exactly what a customer
 * placing the same order would see — flags included, when ProxyPanel provides them.
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
     * The reference's product lines: one row per "+ Add Another Product". `configOptions` is
     * keyed by core `ConfigOption` id, `checkoutConfig` by a server field's own `name` — the
     * same two shapes {@see \App\Livewire\Products\Checkout} binds to, so
     * {@see \Paymenter\Extensions\Others\AdminOps\Support\ProductConfig} can share its logic
     * with checkout instead of duplicating it.
     *
     * @var array<int, array{productId: int|string|null, planId: int|string|null, quantity: int|string, priceOverride: string, domain: string, configOptions: array<int, mixed>, checkoutConfig: array<string, mixed>}>
     */
    public array $items = [];

    /** The reference's Order Status dropdown. Active skips Pending and provisions now. */
    public string $orderStatus = 'pending';

    public bool $generateInvoice = true;

    public bool $sendEmail = true;

    /**
     * The reference's credit-balance choice. Only meaningful when {@see creditEligible()} —
     * a line with no invoice, or a balance that does not fully cover it, has nothing to
     * apply — but the property still needs a default, and "apply what's there" is the
     * reference's own: its radio is pre-selected on the Apply option whenever it is offered.
     */
    public bool $applyCredit = true;

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
        return [
            'productId' => null, 'planId' => null, 'quantity' => 1, 'priceOverride' => '', 'domain' => '',
            'configOptions' => [], 'checkoutConfig' => [],
        ];
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

    /**
     * A product pick defaults its line to the product's first plan and its options' own
     * defaults — mirroring what picking a product does on the storefront's checkout.
     */
    public function updatedItems($value, ?string $key = null): void
    {
        if ($key === null || !str_ends_with($key, '.productId')) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $this->items[$index]['planId'] = $this->plansFor($value)->first()?->id;
        $this->items[$index]['configOptions'] = ProductConfig::defaultConfigOptions(ProductConfig::configOptions($value), []);
        $this->items[$index]['checkoutConfig'] = ProductConfig::defaultCheckoutConfig(ProductConfig::checkoutConfig($value), []);
    }

    public function plansFor($productId)
    {
        return $productId
            ? Plan::where('priceable_type', Product::class)->where('priceable_id', $productId)->get()
            : collect();
    }

    /**
     * This client's spendable balance in the order's currency — the same row core's own
     * {@see \App\Livewire\Invoices\Show::payWithCredit()} and ClientTools' `ApplyCredit`
     * read, just for whichever client is picked here instead of the logged-in one.
     */
    public function creditBalance(): float
    {
        if (!$this->userId) {
            return 0.0;
        }

        $currency = config('settings.default_currency', 'USD');

        return (float) (User::find($this->userId)
            ?->credits()->where('currency_code', $currency)->first()?->amount ?? 0);
    }

    /** The live Order Summary: a line per picked product, unit price or override, options included. */
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
            $options = ProductConfig::configOptions($item['productId']);
            $checkoutFields = ProductConfig::checkoutConfig($item['productId'], $item['checkoutConfig']);
            $delta = $plan ? ProductConfig::priceDelta($options, $item['configOptions'], $plan) : ['price' => 0.0, 'setup_fee' => 0.0];

            $unit = $item['priceOverride'] !== '' && is_numeric($item['priceOverride'])
                ? (float) $item['priceOverride']
                : (float) ($plan?->price($currency)->price ?? 0) + $delta['price'];
            $quantity = max(1, (int) $item['quantity']);

            $lines[] = [
                'index' => $index,
                'label' => $products[$item['productId']]?->name ?? '—',
                'plan' => $plan,
                'unit' => $unit,
                'quantity' => $quantity,
                'total' => $unit * $quantity,
                'domain' => trim($item['domain']),
                'options' => $options,
                'checkoutFields' => $checkoutFields,
                'notes' => ProductConfig::summaryLines($options, $item['configOptions'], $checkoutFields, $item['checkoutConfig']),
            ];
        }

        // The reference's "Recurring" banner: what keeps billing after this invoice, grouped
        // by cycle rather than assumed singular — a mixed order (a monthly proxy plan and an
        // annual add-on, say) gets one row per cycle, the same way the reference does when
        // its own cart mixes them. A one-time or free line contributes nothing here; it is
        // already the whole of what it will ever cost, and the reference does not label it
        // "recurring" either.
        $recurring = [];
        foreach ($lines as $line) {
            if (!$line['plan'] || in_array($line['plan']->type, ['free', 'one-time'], true)) {
                continue;
            }

            $cycle = ProductConfig::cycleLabel($line['plan']);
            $recurring[$cycle] = ($recurring[$cycle] ?? 0) + $line['total'];
        }

        $total = array_sum(array_column($lines, 'total'));
        $credit = $this->creditBalance();

        return [
            'currency' => $currency,
            'lines' => $lines,
            'total' => $total,
            'recurring' => $recurring,
            'creditBalance' => $credit,
            // Only offered when it fully covers the order, matching the one shape the
            // reference itself shows: "Apply $X … No further payment will be due." A partial
            // balance is never silently part-applied — the admin sees no offer rather than
            // one that would leave the invoice still owing something the form did not say.
            'creditEligible' => $this->generateInvoice && $total > 0 && $credit >= $total,
        ];
    }

    public function create(): void
    {
        $rules = [
            'userId' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.priceOverride' => 'nullable|numeric|min:0',
        ];
        $attributes = ['userId' => 'client'];

        foreach ($this->items as $index => $item) {
            if (!$item['productId']) {
                continue;
            }

            $options = ProductConfig::configOptions($item['productId']);
            $checkoutFields = ProductConfig::checkoutConfig($item['productId'], $item['checkoutConfig']);
            $rules = [...$rules, ...ProductConfig::rules($options, $checkoutFields, "items.$index")];
            $attributes = [...$attributes, ...ProductConfig::attributes($options, $checkoutFields, "items.$index")];
        }

        $this->validate($rules, attributes: $attributes);

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
        $activateNow = $this->orderStatus === 'active';
        $wantsCredit = $this->applyCredit && $summary['creditEligible'];

        // Captured by reference so the credit application below — which needs a real,
        // persisted Invoice — can run after this transaction commits rather than nested
        // inside it for no reason.
        $invoice = null;

        $order = DB::transaction(function () use ($user, $summary, $activateNow, &$invoice): Order {
            $order = new Order([
                'user_id' => $user->id,
                'currency_code' => $summary['currency'],
            ]);
            // The reference's Send Email checkbox — the observer sends the order
            // confirmation unless told not to.
            $order->send_create_email = $this->sendEmail;
            $order->save();

            if ($this->generateInvoice && $summary['total'] > 0) {
                $invoice = new Invoice([
                    'user_id' => $user->id,
                    'due_at' => now()->addDays(7),
                    'currency_code' => $summary['currency'],
                ]);
                $invoice->save();
            }

            foreach ($summary['lines'] as $line) {
                $item = $this->items[$line['index']];

                $service = $order->services()->create([
                    'user_id' => $user->id,
                    'currency_code' => $summary['currency'],
                    'product_id' => $item['productId'],
                    'plan_id' => $line['plan']->id,
                    'price' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'coupon_id' => $this->couponId,
                    'status' => Service::STATUS_PENDING,
                ]);

                if ($line['domain'] !== '') {
                    $service->properties()->updateOrCreate(['key' => 'domain'], ['name' => 'Domain', 'value' => $line['domain']]);
                }

                ProductConfig::persist($service, $line['options'], $item['configOptions'], $line['checkoutFields'], $item['checkoutConfig']);

                // Order Status: Active. The same path a free checkout takes in
                // App\Livewire\Cart::checkout() — provision now, skip Pending. This is for
                // payment collected outside the system (bank transfer, cash): the invoice
                // below still records what is owed, but the service does not wait on it.
                if ($activateNow) {
                    if ($service->product?->server) {
                        CreateJob::dispatch($service);
                    }
                    $service->status = Service::STATUS_ACTIVE;
                    $service->expires_at = $service->calculateNextDueDate();
                    $service->save();
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

        // The reference's credit-balance choice. Applied after the order's own transaction
        // commits, in its own — exactly the shape core's App\Livewire\Invoices\Show::
        // payWithCredit() and ClientTools' ApplyCredit use to spend a balance against an
        // invoice: lock the credit row and the invoice together, clamp to what is genuinely
        // still owed and genuinely still there, decrement the one and pay the other. Nothing
        // here is a new payment mechanism — it is that same, already-proven call, made for
        // whichever client is picked on this form instead of the one signed into the client
        // area. Re-checked from scratch rather than trusting $summary['creditEligible']:
        // between the summary being computed and the order actually being placed, the
        // balance could have been spent elsewhere.
        $creditApplied = 0.0;

        if ($invoice && $wantsCredit) {
            DB::transaction(function () use ($user, $invoice, &$creditApplied): void {
                $credit = $user->credits()->where('currency_code', $invoice->currency_code)->lockForUpdate()->first();
                $freshInvoice = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

                if (!$credit || $credit->amount <= 0 || !$freshInvoice) {
                    return;
                }

                $apply = round(min((float) $credit->amount, (float) $freshInvoice->remaining), 2);

                if ($apply <= 0) {
                    return;
                }

                $credit->amount -= $apply;
                $credit->save();

                ExtensionHelper::addPayment($freshInvoice->id, null, amount: $apply, isCreditTransaction: true);
                $creditApplied = $apply;
            });
        }

        Notification::make()->title('Order #' . $order->id . ' placed')
            ->body(
                count($summary['lines']) . ' product(s) for ' . $user->name
                . ($creditApplied > 0 ? ' — $' . number_format($creditApplied, 2) . ' ' . $summary['currency'] . ' applied from account credit' : ''),
            )
            ->success()->send();

        $this->redirect(ManageOrders::getUrl(['status' => $activateNow ? 'active' : 'pending']));
    }

    protected function getViewData(): array
    {
        return [
            'clients' => User::whereNull('role_id')->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'email']),
            'gateways' => Gateway::where('enabled', true)->get(['id', 'name']),
            'coupons' => Coupon::query()->orderBy('code')->limit(100)->get(['id', 'code']),
            'products' => Product::with('category')->orderBy('name')->get(['id', 'name', 'category_id']),
            'plansByItem' => collect($this->items)->map(fn ($item) => $this->plansFor($item['productId'])),
            'optionsByItem' => collect($this->items)->map(fn ($item) => ProductConfig::configOptions($item['productId'])),
            'checkoutFieldsByItem' => collect($this->items)->map(fn ($item) => ProductConfig::checkoutConfig($item['productId'], $item['checkoutConfig'])),
            'summary' => $this->summary(),
        ];
    }
}
