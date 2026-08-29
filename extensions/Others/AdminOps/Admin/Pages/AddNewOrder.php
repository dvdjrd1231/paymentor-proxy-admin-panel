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
 * status, the product block (product, domain, billing cycle, quantity, price override), and
 * the Order Summary card with Submit Order.
 *
 * The creation path is a copy of what checkout does ({@see \App\Livewire\Cart::checkout()}):
 * an Order row, a pending Service per product, and — when Generate Invoice is ticked — an
 * Invoice with one item per service, due in seven days. Same tables, same shapes, so
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

    public ?int $productId = null;

    public ?int $planId = null;

    public int $quantity = 1;

    public string $priceOverride = '';

    /** The reference's Domain field — collected, stored as a service property, not required. */
    public string $domain = '';

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

    public function updatedProductId(): void
    {
        $this->planId = $this->plans()->first()?->id;
    }

    /** The live Order Summary: unit price from the plan, or the override when given. */
    public function summary(): array
    {
        $currency = config('settings.default_currency', 'USD');
        $plan = $this->planId ? Plan::with('prices')->find($this->planId) : null;
        $unit = $this->priceOverride !== '' && is_numeric($this->priceOverride)
            ? (float) $this->priceOverride
            : (float) ($plan?->price($currency)->price ?? 0);
        $quantity = max(1, $this->quantity);

        return [
            'currency' => $currency,
            'label' => $this->productId ? Product::find($this->productId)?->name : null,
            'unit' => $unit,
            'quantity' => $quantity,
            'total' => $unit * $quantity,
        ];
    }

    public function create(): void
    {
        $this->validate([
            'userId' => 'required|exists:users,id',
            'productId' => 'required|exists:products,id',
            'planId' => 'required|exists:plans,id',
            'quantity' => 'required|integer|min:1',
            'priceOverride' => 'nullable|numeric|min:0',
        ], attributes: ['userId' => 'client', 'productId' => 'product', 'planId' => 'billing cycle']);

        $user = User::whereNull('role_id')->findOrFail($this->userId);
        $summary = $this->summary();

        $order = DB::transaction(function () use ($user, $summary): Order {
            $order = new Order([
                'user_id' => $user->id,
                'currency_code' => $summary['currency'],
            ]);
            // The reference's Send Email checkbox — the observer sends the order
            // confirmation unless told not to.
            $order->send_create_email = $this->sendEmail;
            $order->save();

            $service = $order->services()->create([
                'user_id' => $user->id,
                'currency_code' => $summary['currency'],
                'product_id' => $this->productId,
                'plan_id' => $this->planId,
                'price' => $summary['unit'],
                'quantity' => $summary['quantity'],
                'coupon_id' => $this->couponId,
                'status' => Service::STATUS_PENDING,
            ]);

            if ($this->domain !== '') {
                $service->properties()->updateOrCreate(['key' => 'domain'], ['name' => 'Domain', 'value' => $this->domain]);
            }

            if ($this->generateInvoice && $summary['total'] > 0) {
                $invoice = new Invoice([
                    'user_id' => $user->id,
                    'due_at' => now()->addDays(7),
                    'currency_code' => $summary['currency'],
                ]);
                $invoice->save();

                $invoice->items()->create([
                    'reference_id' => $service->id,
                    'reference_type' => Service::class,
                    'price' => $summary['total'],
                    'quantity' => $summary['quantity'],
                    'description' => $service->description,
                ]);
            }

            return $order;
        });

        Notification::make()->title('Order #' . $order->id . ' placed')
            ->body($summary['label'] . ' for ' . $user->name)
            ->success()->send();

        $this->redirect(ManageOrders::getUrl(['status' => 'pending']));
    }

    public function plans()
    {
        return $this->productId
            ? Plan::where('priceable_type', Product::class)->where('priceable_id', $this->productId)->get()
            : collect();
    }

    protected function getViewData(): array
    {
        return [
            'clients' => User::whereNull('role_id')->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'email']),
            'gateways' => Gateway::where('enabled', true)->get(['id', 'name']),
            'coupons' => Coupon::query()->orderBy('code')->limit(100)->get(['id', 'code']),
            'products' => Product::with('category')->orderBy('name')->get(['id', 'name', 'category_id']),
            'plans' => $this->plans(),
            'summary' => $this->summary(),
        ];
    }
}
