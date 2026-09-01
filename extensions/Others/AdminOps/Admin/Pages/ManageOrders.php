<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OrderResource;
use App\Models\Order;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Manage Orders, to its screenshot: ID, Order #, Date, Client Name, Payment Method,
 * Total, Payment Status, Status, and the red delete dot — one page for List All / Pending /
 * Active / Cancelled, told apart by `?status=`, exactly as the reference's sidebar does it.
 *
 * A Paymenter order is a container of services with no status of its own, so the Status
 * column is derived from what the services are doing ({@see statusOf()}), and Payment
 * Status from whether the order's invoices are settled. The Order # column shows the
 * order's first invoice number — the one number that actually exists; the reference's
 * random ten digits identify nothing here.
 */
class ManageOrders extends Page
{
    protected string $view = 'adminops::pages.manage-orders';

    protected static ?string $slug = 'manage-orders';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 100;

    /** '' | pending | active | suspended | cancelled — the reference's sidebar filters. */
    #[Url]
    public string $status = '';

    #[Url]
    public string $q = '';

    #[Url]
    public int $page = 1;

    public bool $filter = false;

    /** The rows ticked for the With Selected bar. */
    public array $selected = [];

    /** The row awaiting the "Are you sure?" before deletion. */
    public ?int $confirmingDelete = null;

    /**
     * With Selected → Accept Order, the reference's green button: every pending service on
     * the ticked orders activates, provisioned the way checkout's zero-total path does.
     */
    public function acceptSelected(): void
    {
        $count = 0;

        DB::transaction(function () use (&$count): void {
            $orders = Order::with('services.product')->whereIn('id', array_map('intval', $this->selected))->get();

            foreach ($orders as $order) {
                foreach ($order->services->where('status', 'pending') as $service) {
                    if ($service->product?->server) {
                        \App\Jobs\Server\CreateJob::dispatch($service);
                    }

                    $service->status = 'active';
                    $service->expires_at = $service->calculateNextDueDate();
                    $service->save();
                    $count++;
                }
            }
        });

        $this->selected = [];
        Notification::make()->title($count ? 'Accepted: ' . $count . ' service(s) activated' : 'Nothing pending in the selected orders')
            ->{$count ? 'success' : 'warning'}()->send();
    }

    /** With Selected → Cancel Order: pending and active services on the ticked orders stop. */
    public function cancelSelected(): void
    {
        $count = 0;

        DB::transaction(function () use (&$count): void {
            $orders = Order::with('services')->whereIn('id', array_map('intval', $this->selected))->get();

            foreach ($orders as $order) {
                foreach ($order->services->whereIn('status', ['pending', 'active', 'suspended']) as $service) {
                    $service->update(['status' => 'cancelled']);
                    $count++;
                }
            }
        });

        $this->selected = [];
        Notification::make()->title($count ? 'Cancelled: ' . $count . ' service(s)' : 'Nothing running in the selected orders')
            ->{$count ? 'success' : 'warning'}()->send();
    }

    /**
     * The reference's third bulk button. Same guard as the per-row delete: an order whose
     * services are live is not paperwork to throw away, and invoices stay regardless.
     */
    public function deleteSelected(): void
    {
        $deleted = 0;
        $refused = 0;

        DB::transaction(function () use (&$deleted, &$refused): void {
            foreach (Order::with('services')->whereIn('id', array_map('intval', $this->selected))->get() as $order) {
                if ($order->services->whereIn('status', ['active', 'suspended'])->isNotEmpty()) {
                    $refused++;

                    continue;
                }

                $order->services()->delete();
                $order->delete();
                $deleted++;
            }
        });

        $this->selected = [];
        Notification::make()
            ->title($deleted . ' order(s) deleted' . ($refused ? ', ' . $refused . ' kept' : ''))
            ->body($refused ? 'Orders with active or suspended services cannot be deleted.' : null)
            ->{$deleted ? 'success' : 'warning'}()->send();
    }

    public static function canAccess(): bool
    {
        return OrderResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Manage Orders';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function search(): void
    {
        $this->page = 1;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function askDelete(int $id): void
    {
        $this->confirmingDelete = $id;
    }

    public function deleteOrder(): void
    {
        if (!$this->confirmingDelete) {
            return;
        }

        $order = Order::with('services')->findOrFail($this->confirmingDelete);
        $this->confirmingDelete = null;

        // Deleting a running order would strand a provisioned service; the reference
        // refuses the same way. Cancelled and never-provisioned rows may go.
        if ($order->services->whereIn('status', ['active', 'suspended'])->isNotEmpty()) {
            Notification::make()->title('Cannot delete')
                ->body('This order has active or suspended services — cancel them first.')
                ->danger()->send();

            return;
        }

        DB::transaction(function () use ($order): void {
            // Invoices stay: they are accounting records, and the reference keeps them too.
            $order->services()->delete();
            $order->delete();
        });

        Notification::make()->title('Order deleted')->success()->send();
    }

    /**
     * The reference's ten-digit Order # — a stable number derived from the id, because
     * Paymenter's orders table has no number column and core must stay unedited. Same id,
     * same number, forever; the id column beside it remains the real key.
     */
    public static function numberOf(Order $order): string
    {
        return str_pad((string) (crc32('paymenter-order-' . $order->id) % 10_000_000_000), 10, '0', STR_PAD_LEFT);
    }

    /** The derived Status column: what the order's services are collectively doing. */
    public static function statusOf(Order $order): array
    {
        $statuses = $order->services->pluck('status');

        return match (true) {
            $statuses->contains('active') => ['Active', 'ao-mo-active'],
            $statuses->contains('pending') => ['Pending', 'ao-mo-pending'],
            $statuses->contains('suspended') => ['Suspended', 'ao-mo-suspended'],
            $statuses->contains('cancelled') => ['Terminated', 'ao-mo-terminated'],
            default => ['—', ''],
        };
    }

    /** Payment Status + Payment Method, from the order's invoices and their transactions. */
    public static function paymentOf(Order $order): array
    {
        $invoices = $order->services->flatMap->invoices->unique('id');

        if ($invoices->isEmpty()) {
            return ['method' => '—', 'label' => '—', 'class' => '', 'number' => null];
        }

        $gateway = $invoices->flatMap->transactions->first()?->gateway?->name;
        $paid = $invoices->every(fn ($invoice) => $invoice->status === 'paid');

        return [
            'method' => $gateway ?? '—',
            'label' => $paid ? 'Complete' : 'Incomplete',
            'class' => $paid ? 'ao-mo-complete' : 'ao-mo-incomplete',
            'number' => $invoices->first()->number,
        ];
    }

    protected function getViewData(): array
    {
        $orders = $this->query()->paginate(self::PER_PAGE, page: $this->page);

        if ($this->page > 1 && $orders->isEmpty()) {
            $this->page = max(1, $orders->lastPage());
            $orders = $this->query()->paginate(self::PER_PAGE, page: $this->page);
        }

        return ['orders' => $orders];
    }

    private function query()
    {
        $query = Order::query()
            ->with(['user', 'currency', 'services.product', 'services.invoices.transactions.gateway'])
            ->orderByDesc('id');

        match ($this->status) {
            'pending' => $query->whereHas('services', fn ($q) => $q->where('status', 'pending')),
            'active' => $query->whereHas('services', fn ($q) => $q->where('status', 'active')),
            'suspended' => $query->whereHas('services', fn ($q) => $q->where('status', 'suspended')),
            // Cancelled the reference's way: every service terminated, none still going.
            'cancelled' => $query->whereHas('services')
                ->whereDoesntHave('services', fn ($q) => $q->whereIn('status', ['pending', 'active', 'suspended'])),
            // Paymenter has no fraud flag, so the reference's filter honestly lists nothing.
            'fraud' => $query->whereRaw('1 = 0'),
            default => null,
        };

        if ($this->q !== '') {
            $query->where(function ($outer): void {
                $outer->whereHas('user', function ($q): void {
                    $q->where('first_name', 'like', '%' . $this->q . '%')
                        ->orWhere('last_name', 'like', '%' . $this->q . '%')
                        ->orWhere('email', 'like', '%' . $this->q . '%');
                });

                if (ctype_digit($this->q)) {
                    $outer->orWhere('id', (int) $this->q);
                }
            });
        }

        return $query;
    }
}
