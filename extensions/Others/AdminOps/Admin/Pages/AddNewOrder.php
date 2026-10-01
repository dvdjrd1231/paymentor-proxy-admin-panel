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
     * @var array<int, array{productId: int|string|null, planId: int|string|null, quantity: int|string, priceOverride: string, domain: string, configOptions: array<int, mixed>, checkoutConfig: array<string, mixed>, customFields: array<string, mixed>}>
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

    /**
     * The reference's Domain Registration blocks, interactive (Leandro, 2026-09-10).
     * No TLD is configured for sale on this store — there is no registrar — so at
     * submit a requested domain is omitted, with the reference's own Order Summary
     * note saying exactly that. The reference behaves the same way for a TLD it
     * cannot sell.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $domains = [];

    private static function blankDomain(): array
    {
        return [
            'type' => 'none', 'domain' => '', 'period' => '1', 'epp' => '',
            'dns' => false, 'email_fwd' => false, 'id_protect' => false,
            'reg_price' => '', 'renew_price' => '',
        ];
    }

    public function addDomain(): void
    {
        $this->domains[] = self::blankDomain();
    }

    public function mount(): void
    {
        $this->items = [self::blankItem()];
        $this->domains = [self::blankDomain()];

        // No "Default" row — the reference preselects the first configured gateway.
        $this->gatewayId ??= collect(\Paymenter\Extensions\Others\AdminOps\Support\GatewayOrder::sort(
            Gateway::where('enabled', true)->get(['id', 'name']),
        ))->first()?->id;
    }

    private static function blankItem(): array
    {
        return [
            'productId' => null, 'planId' => null, 'quantity' => 1, 'priceOverride' => '', 'domain' => '',
            'configOptions' => [], 'checkoutConfig' => [], 'customFields' => [],
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
        // The custom select entangles strings ('' for None, '5' for a product) and
        // ProductConfig's signatures are typed ?int — passing the raw value 500'd every
        // product pick as an "Error while loading page" toast (Leandro's log, 2026-09-04).
        $productId = ctype_digit((string) $value) ? (int) $value : null;
        $this->items[$index]['productId'] = $productId;
        $this->items[$index]['planId'] = $this->plansFor($productId)->first()?->id;
        $this->items[$index]['configOptions'] = ProductConfig::defaultConfigOptions(ProductConfig::configOptions($productId), []);
        $this->items[$index]['checkoutConfig'] = ProductConfig::defaultCheckoutConfig(ProductConfig::checkoutConfig($productId), []);
    }

    /**
     * A line's product as an id, or null for None.
     *
     * The picker entangles strings — '' for None, '31' for a product — and every
     * ProductConfig signature is typed ?int, so the '' went in raw and threw
     * "must be of type ?int, string given". The page then rendered as the reference's
     * "Error while loading page" toast, which is what picking None did every time
     * (Leandro, #10: "It returns an error"). An earlier fix cast only the local copy
     * inside updatedItems(), leaving the stored value a string for this to trip over.
     */
    private static function productIdOf(array $item): ?int
    {
        return ctype_digit((string) ($item['productId'] ?? '')) ? (int) $item['productId'] : null;
    }

    public function plansFor($productId)
    {
        return $productId
            ? Plan::where('priceable_type', Product::class)->where('priceable_id', $productId)->get()
            : collect();
    }

    /**
     * The reference's Custom Fields block: the fields an admin fills in on the order itself.
     *
     * WHMCS drives these from the product's own custom fields; Paymenter's equivalent is a
     * custom property scoped to Service, so the block is built from those rather than from
     * a hard-coded list. An install that has defined none shows no block, which is why this
     * screen has none today — all seventeen properties here are scoped to User.
     *
     * Deliberately not the module's own storage: ProxyPanel keeps the remote service id,
     * the api key and the endpoints as service properties it writes during provisioning.
     * Offering those three as inputs, as the reference's proxy module does, would let an
     * admin type a value that provisioning then overwrites — or point a service at someone
     * else's remote proxy.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\CustomProperty>
     */
    public static function customFields()
    {
        return \App\Models\CustomProperty::query()
            ->where('model', Service::class)
            ->where('non_editable', false)
            ->get();
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
        $products = Product::with('category')
            ->whereIn('id', collect($this->items)->pluck('productId')->filter())->get()->keyBy('id');

        $lines = [];
        foreach ($this->items as $index => $item) {
            if (!$item['productId']) {
                continue;
            }

            // "x:Quarterly" means a cycle this product prices nothing for — the row says
            // so; there is no plan to load, and it must never reach the query.
            $plan = is_numeric($item['planId']) ? Plan::with('prices')->find($item['planId']) : null;
            $options = ProductConfig::configOptions($item['productId']);
            $checkoutFields = ProductConfig::checkoutConfig($item['productId'], $item['checkoutConfig']);
            $delta = $plan ? ProductConfig::priceDelta($options, $item['configOptions'], $plan) : ['price' => 0.0, 'setup_fee' => 0.0];

            $unit = $item['priceOverride'] !== '' && is_numeric($item['priceOverride'])
                ? (float) $item['priceOverride']
                : (float) ($plan?->price($currency)->price ?? 0) + $delta['price'];
            $quantity = max(1, (int) $item['quantity']);

            $lines[] = [
                'index' => $index,
                // The reference's wording: "1 x Category - Product", cycle underneath.
                'label' => trim(($products[$item['productId']]?->category?->name ? $products[$item['productId']]->category->name . ' - ' : '')
                    . ($products[$item['productId']]?->name ?? '—')),
                'cycle' => $plan ? ProductConfig::cycleLabel($plan) : null,
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
            // Any balance at all is offered, as the reference does. It carries two wordings
            // for this — "Apply :amount … No further payment will be due." when the balance
            // covers the order, and "Apply :amount … and client will pay the remaining
            // amount via the selected payment method." when it does not
            // (orders.applyCreditAmountNoFurtherPayment / orders.applyCreditAmount). Ours
            // required full cover and so showed a client with some credit no option at all,
            // which read as the choice having been taken away (Leandro, #10). Placing the
            // order already applies whichever is smaller, so the partial case was supported
            // everywhere except on the form.
            'creditEligible' => $this->generateInvoice && $total > 0 && $credit > 0,
            'creditApplicable' => min($credit, $total),
            'creditCoversTotal' => $credit >= $total,
        ];
    }

    public function create(): void
    {
        // No product line chosen is already caught below (`'Pick at least one product.'`)
        // after validate() runs — real server enforcement, not the removed <select>'s
        // browser-only `required`. Issue #10's custom dropdown needed nothing added here.
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
                $wanted = (string) ($this->items[$line['index']]['planId'] ?? '');

                $this->addError('items', str_starts_with($wanted, 'x:')
                    ? 'This product has no ' . substr($wanted, 2) . ' price. Give it one under Products/Services, or pick a cycle it prices.'
                    : 'Every product line needs a billing cycle.');

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

            // The Payment Method picked here — ManageOrders::paymentOf() reads it back
            // until a transaction names the gateway that really paid.
            if ($this->gatewayId) {
                \Paymenter\Extensions\Others\AdminOps\Models\Meta::put($order, 'gateway', $this->gatewayId);
            }

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

                // The reference's Custom Fields, stored on the service they were entered
                // for. A blank one writes nothing rather than an empty property.
                foreach (static::customFields() as $field) {
                    $value = $item['customFields'][$field->key] ?? null;

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $service->properties()->updateOrCreate(
                        ['key' => $field->key],
                        ['name' => $field->name, 'value' => is_bool($value) ? (int) $value : $value],
                    );
                }

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
            'clients' => User::whereNull('role_id')->orderBy('first_name')->limit(500)
                ->with('properties')->get(['id', 'first_name', 'last_name', 'email']),
            'gateways' => \Paymenter\Extensions\Others\AdminOps\Support\GatewayOrder::sort(Gateway::where('enabled', true)->get(['id', 'name'])),
            'coupons' => Coupon::query()->orderBy('code')->limit(100)->get(['id', 'code']),
            // In the catalogue's own order, not alphabetically: category by its sort then
            // its name, each product by the same, which is exactly how the rail lists them
            // (Leandro, #10: "ajustar ordenacao dos itens similar a organizacao do
            // cadastro"). Sorted here rather than in SQL so the category's own ordering is
            // read from the relation instead of a join that would have to alias both names.
            'products' => Product::with('category')->get(['id', 'name', 'category_id', 'sort'])
                ->sortBy(fn (Product $product): string => sprintf(
                    '%03d%s|%03d%s',
                    $product->category?->sort ?? 255,
                    $product->category?->name ?? '~',
                    $product->sort ?? 255,
                    $product->name,
                ))
                ->values(),
            'plansByItem' => collect($this->items)->map(fn ($item) => $this->plansFor(static::productIdOf($item))),
            'optionsByItem' => collect($this->items)->map(fn ($item) => ProductConfig::configOptions(static::productIdOf($item))),
            'checkoutFieldsByItem' => collect($this->items)->map(fn ($item) => ProductConfig::checkoutConfig(static::productIdOf($item), $item['checkoutConfig'])),
            'customFields' => static::customFields(),
            'summary' => $this->summary(),
        ];
    }
}
