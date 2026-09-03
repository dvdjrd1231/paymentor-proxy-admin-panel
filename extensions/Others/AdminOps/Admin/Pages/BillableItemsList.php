<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\BillableItems\Models\BillableItem;

/**
 * Issue #13 — WHMCS's Billable Items: the list with its Uninvoiced and Recurring views,
 * and the Add New form — client, description, quantity and amount, the Invoice Action,
 * the recurrence. Rows live in the BillableItems extension; its own sweeper invoices them.
 */
class BillableItemsList extends Page
{
    protected string $view = 'adminops::pages.billable-items-list';

    protected static ?string $slug = 'manage-billable-items';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'view')]
    public string $tab = 'all';

    /** The reference's Search/Filter panel, field for field: Client, Description, Amount, Status. */
    #[Url]
    public bool $filter = false;

    #[Url]
    public string $q = '';

    #[Url]
    public string $client = '';

    #[Url]
    public string $famount = '';

    /** '' | uninvoiced | invoiced | recurring — states the columns themselves show. */
    #[Url]
    public string $status = '';

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;

        if ($this->filter) {
            $this->adding = false;
        }
    }

    #[Url]
    public bool $adding = false;

    public ?int $userId = null;

    /** The reference's Product/Service: the charge can belong to one of the client's services. */
    public ?int $serviceId = null;

    public string $description = '';

    public string $quantity = '1';

    public string $amount = '';

    public string $action = BillableItem::ACTION_NEXT_INVOICE;

    public string $recur = '';

    public string $dueDate = '';

    /** Picking a client empties the service choice — the old client's services are not theirs. */
    public function updatedUserId(): void
    {
        $this->serviceId = null;
    }

    public static function canAccess(): bool
    {
        return class_exists(BillableItem::class)
            && (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Billable Items';
    }

    public function toggleAdding(): void
    {
        $this->adding = !$this->adding;
    }

    public function create(): void
    {
        $this->validate([
            'userId' => 'required|exists:users,id',
            'description' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01',
            'amount' => 'required|numeric|min:0',
            'action' => 'in:' . implode(',', [BillableItem::ACTION_NEXT_INVOICE, BillableItem::ACTION_IMMEDIATELY, BillableItem::ACTION_HOLD]),
            'recur' => 'nullable|in:week,month,quarter,year',
            'dueDate' => 'nullable|date',
        ], attributes: ['userId' => 'client']);

        BillableItem::create([
            'user_id' => $this->userId,
            'service_id' => $this->serviceId,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'amount' => (float) $this->amount,
            'currency_code' => config('settings.default_currency', 'USD'),
            'invoice_action' => $this->action,
            'recur_every' => $this->recur ?: null,
            'next_due_at' => $this->dueDate ?: null,
            'admin_id' => Auth::id(),
        ]);

        $this->adding = false;
        $this->reset(['userId', 'description', 'amount', 'recur', 'dueDate']);
        $this->quantity = '1';
        Notification::make()->title('Billable item added')->success()->send();
    }

    /** @var array<int, string> The ticked rows, for the reference's With Selected bar. */
    public array $selected = [];

    /** The reference's first bulk button: hand the items to the daily invoice run. */
    public function invoiceSelected(): void
    {
        $queued = BillableItem::whereIn('id', array_map('intval', array_filter($this->selected)))
            ->whereNull('invoiced_at')
            ->update(['invoice_action' => BillableItem::ACTION_IMMEDIATELY]);

        $this->selected = [];
        Notification::make()->title($queued . ' item(s) queued for the next run')
            ->{$queued ? 'success' : 'warning'}()->send();
    }

    public function deleteSelected(): void
    {
        $items = BillableItem::whereIn('id', array_map('intval', array_filter($this->selected)))->get();
        $deleted = $items->whereNull('invoiced_at');
        $kept = $items->count() - $deleted->count();

        BillableItem::whereIn('id', $deleted->pluck('id'))->delete();

        $this->selected = [];
        Notification::make()
            ->title($deleted->count() . ' item(s) deleted' . ($kept ? ', ' . $kept . ' kept' : ''))
            ->body($kept ? 'Invoiced items keep their paperwork.' : null)
            ->{$deleted->count() ? 'success' : 'warning'}()->send();
    }

    public function delete(int $id): void
    {
        $item = BillableItem::findOrFail($id);

        if ($item->invoiced_at) {
            Notification::make()->title('Cannot delete')->body('This item is already invoiced.')->danger()->send();

            return;
        }

        $item->delete();
        Notification::make()->title('Billable item deleted')->success()->send();
    }

    protected function getViewData(): array
    {
        $items = BillableItem::with(['user'])
            ->when($this->tab === 'uninvoiced', fn ($q) => $q->whereNull('invoiced_at'))
            ->when($this->tab === 'recurring', fn ($q) => $q->whereNotNull('recur_every'))
            ->when(trim($this->q) !== '', function ($query) {
                $needle = trim($this->q);
                $query->where(fn ($w) => $w->where('description', 'like', "%{$needle}%")
                    ->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$needle}%")
                        ->orWhere('last_name', 'like', "%{$needle}%")
                        ->orWhere('email', 'like', "%{$needle}%")));
            })
            ->when(trim($this->client) !== '', function ($query) {
                $needle = trim($this->client);
                $query->whereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$needle}%")
                    ->orWhere('last_name', 'like', "%{$needle}%")
                    ->orWhere('email', 'like', "%{$needle}%"));
            })
            ->when(trim($this->famount) !== '' && is_numeric(trim($this->famount)),
                fn ($query) => $query->where('amount', (float) trim($this->famount)))
            ->when($this->status === 'uninvoiced', fn ($q) => $q->whereNull('invoiced_at'))
            ->when($this->status === 'invoiced', fn ($q) => $q->whereNotNull('invoiced_at'))
            ->when($this->status === 'recurring', fn ($q) => $q->whereNotNull('recur_every'))
            ->latest('id')->limit(200)->get();

        return [
            'items' => $items,
            'clients' => User::whereNull('role_id')->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'email']),
            // The picked client's services for the Product/Service select — the charge can
            // name the proxy it belongs to, exactly as the reference relates them.
            'clientServices' => $this->userId
                ? \App\Models\Service::with('product')->where('user_id', $this->userId)
                    ->whereIn('status', ['active', 'suspended', 'pending'])->latest('id')->limit(100)->get()
                : collect(),
            'actions' => [
                BillableItem::ACTION_NEXT_INVOICE => "Add to the customer's next invoice",
                BillableItem::ACTION_IMMEDIATELY => 'Invoice on the next daily run',
                BillableItem::ACTION_HOLD => "Don't invoice for now",
            ],
        ];
    }
}
