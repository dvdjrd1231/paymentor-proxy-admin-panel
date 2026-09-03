<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
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

    // The reference's Search/Filter panel, field for field. Every one is a URL, so a
    // filtered list can be pasted into a ticket.
    #[Url]
    public string $oid = '';

    /** The ten-digit Order # — matched against {@see numberOf()}, since no column holds it. */
    #[Url]
    public string $onum = '';

    /** "MM/DD/YYYY - MM/DD/YYYY", or a single date — the reference's one range field. */
    #[Url]
    public string $dates = '';

    /** Exact order total; derived in PHP because core's `total` is an accessor, not a column. */
    #[Url]
    public string $amount = '';

    /** Client name or email; the input offers a typeahead list of real clients. */
    #[Url]
    public string $client = '';

    /** '' | complete | incomplete — the Payment Status column's own two values. */
    #[Url]
    public string $pay = '';

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
        $orders = $this->paginated();

        if ($this->page > 1 && $orders->isEmpty()) {
            $this->page = max(1, $orders->lastPage());
            $orders = $this->paginated();
        }

        return [
            'orders' => $orders,
            // The Client field's typeahead: real clients, as the reference's picker offers.
            'clientOptions' => User::query()->whereNull('role_id')
                ->orderBy('first_name')->limit(500)
                ->get(['first_name', 'last_name', 'email'])
                ->map(fn (User $user): string => trim($user->first_name . ' ' . $user->last_name) . ' (' . $user->email . ')')
                ->all(),
        ];
    }

    /**
     * Order #, Amount and Payment Status exist only in PHP — the number is derived from the
     * id, the total is an accessor over services, and payment state reads the invoices — so
     * when any of them is set the SQL-filtered set is filtered here and paged by hand. The
     * relations are eager either way, so this walks nothing the grid was not about to render.
     */
    private function paginated(): LengthAwarePaginator
    {
        if ($this->onum === '' && $this->amount === '' && $this->pay === '') {
            return $this->query()->paginate(self::PER_PAGE, page: $this->page);
        }

        $all = $this->query()->get()
            ->filter(function (Order $order): bool {
                if ($this->onum !== '' && !str_contains(static::numberOf($order), preg_replace('/\D/', '', $this->onum))) {
                    return false;
                }

                if ($this->amount !== '' && is_numeric($this->amount)
                    && abs((float) $order->total - (float) $this->amount) >= 0.005) {
                    return false;
                }

                if ($this->pay !== '') {
                    $label = static::paymentOf($order)['label'];

                    if ($this->pay === 'complete' && $label !== 'Complete') {
                        return false;
                    }

                    if ($this->pay === 'incomplete' && $label !== 'Incomplete') {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        return new LengthAwarePaginator(
            $all->forPage($this->page, self::PER_PAGE)->values(),
            $all->count(),
            self::PER_PAGE,
            $this->page,
        );
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

        if ($this->oid !== '' && ctype_digit(trim($this->oid))) {
            $query->where('id', (int) trim($this->oid));
        }

        if ($this->client !== '') {
            // A typeahead pick arrives as "First Last (email)"; typed text is just text.
            // Either way, match what the person means, not the parentheses.
            $email = preg_match('/\(([^)]+@[^)]+)\)/', $this->client, $m) ? $m[1] : null;
            $name = trim((string) preg_replace('/\s*\([^)]*\)\s*/', ' ', $this->client));

            $query->whereHas('user', function ($q) use ($email, $name): void {
                if ($email !== null) {
                    $q->where('email', $email);

                    return;
                }

                $q->where(function ($inner) use ($name): void {
                    $inner->where('first_name', 'like', '%' . $name . '%')
                        ->orWhere('last_name', 'like', '%' . $name . '%')
                        ->orWhere('email', 'like', '%' . $name . '%')
                        ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ['%' . $name . '%']);
                });
            });
        }

        [$from, $to] = $this->dateRange();

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * The Date Range field, parsed the way the reference writes it: "MM/DD/YYYY - MM/DD/YYYY",
     * or a single date meaning that one day. Unparseable text filters nothing rather than
     * everything.
     *
     * @return array{?Carbon, ?Carbon}
     */
    private function dateRange(): array
    {
        $text = trim($this->dates);

        if ($text === '') {
            return [null, null];
        }

        $parse = function (string $piece): ?Carbon {
            foreach (['m/d/Y', 'Y-m-d', 'd/m/Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, trim($piece));
                } catch (\Throwable $e) {
                }
            }

            return null;
        };

        // Split on a spaced hyphen only — the dates' own slashes and Y-m-d hyphens survive.
        $pieces = preg_split('/\s+[-–]\s+/', $text, 2);
        $from = $parse($pieces[0]);
        $to = isset($pieces[1]) ? $parse($pieces[1]) : $from;

        return [$from, $to];
    }
}
