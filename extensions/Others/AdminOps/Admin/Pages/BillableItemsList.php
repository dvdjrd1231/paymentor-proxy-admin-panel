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

    public bool $adding = false;

    public ?int $userId = null;

    public string $description = '';

    public string $quantity = '1';

    public string $amount = '';

    public string $action = BillableItem::ACTION_NEXT_INVOICE;

    public string $recur = '';

    public string $dueDate = '';

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
            ->latest('id')->limit(200)->get();

        return [
            'items' => $items,
            'clients' => User::whereNull('role_id')->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'email']),
            'actions' => [
                BillableItem::ACTION_NEXT_INVOICE => "Add to the customer's next invoice",
                BillableItem::ACTION_IMMEDIATELY => 'Invoice on the next daily run',
                BillableItem::ACTION_HOLD => "Don't invoice for now",
            ],
        ];
    }
}
