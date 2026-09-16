<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\Invoice;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Create Invoice, reached from a client's profile.
 *
 * Core has its own create screen, but it is a plain Filament form: it arrives with the
 * User box empty, so the client you started from has to be picked again, and it is styled
 * as Filament rather than as the reference (Leandro, issue #53). Its page cannot be
 * pre-filled from the URL either — `CreateRecord::fillForm()` calls `$this->form->fill()`
 * with no arguments — and that file is vendored core. So this is our own screen, carrying
 * the client through and wearing the same skin as the rest of the admin.
 *
 * Creation matches core's own: the invoice row, its items, and `send_create_email`, which
 * is what core's CreateInvoice sets to decide whether the client is told.
 */
class CreateInvoice extends Page
{
    protected string $view = 'adminops::pages.create-invoice';

    protected static ?string $slug = 'create-invoice';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The client this invoice is for — the whole point of the screen. */
    #[Url]
    public ?int $for = null;

    public string $userId = '';

    public string $issuedAt = '';

    public string $dueAt = '';

    public string $status = 'pending';

    public string $currencyCode = '';

    public bool $sendEmail = true;

    /** @var array<int, array{description: string, price: string, quantity: int}> */
    public array $items = [];

    public const STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    public static function canAccess(): bool
    {
        return \App\Admin\Resources\InvoiceResource::canCreate();
    }

    public function getTitle(): string
    {
        return 'Create Invoice';
    }

    public function mount(): void
    {
        $this->userId = $this->for ? (string) $this->for : '';
        $this->issuedAt = now()->format('Y-m-d');
        $this->dueAt = now()->addDays(7)->format('Y-m-d');
        $this->currencyCode = $this->currencyFor($this->for) ?: (string) config('settings.default_currency', 'USD');
        $this->items = [self::blankItem()];
    }

    /** The client's own currency, as core bills them in — not the store default blindly. */
    private function currencyFor(?int $userId): ?string
    {
        return $userId ? User::find($userId)?->currency_code : null;
    }

    /** @return array{description: string, price: string, quantity: int} */
    private static function blankItem(): array
    {
        return ['description' => '', 'price' => '', 'quantity' => 1];
    }

    public function updatedUserId(): void
    {
        $this->currencyCode = $this->currencyFor((int) $this->userId ?: null) ?: $this->currencyCode;
    }

    public function addItem(): void
    {
        $this->items[] = self::blankItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items) ?: [self::blankItem()];
    }

    public function save(): void
    {
        $this->validate([
            'userId' => 'required|exists:users,id',
            'issuedAt' => 'required|date',
            'dueAt' => 'required|date|after_or_equal:issuedAt',
            'status' => 'required|in:' . implode(',', array_keys(self::STATUSES)),
            'currencyCode' => 'required|string|max:8',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.price' => 'required|numeric',
            'items.*.quantity' => 'required|integer|min:1',
        ], attributes: [
            'userId' => 'client',
            'items.*.description' => 'description',
            'items.*.price' => 'amount',
            'items.*.quantity' => 'quantity',
        ]);

        $invoice = DB::transaction(function (): Invoice {
            $invoice = new Invoice;
            $invoice->fill([
                'user_id' => (int) $this->userId,
                'due_at' => $this->dueAt,
                'status' => $this->status,
                'currency_code' => $this->currencyCode,
            ]);
            // The reference's "Issued At" is core's own created_at — the column its form
            // edits — and it is not fillable, so it is set directly. A date box carries no
            // time, so backdating to today would stamp the invoice midnight and sort it
            // behind everything raised earlier the same day; today keeps the real clock.
            if ($this->issuedAt !== now()->format('Y-m-d')) {
                $invoice->created_at = $this->issuedAt;
            }
            // Core's own switch for telling the client, set the way its create page sets it.
            $invoice->send_create_email = $this->sendEmail;
            $invoice->save();

            foreach ($this->items as $row) {
                $invoice->items()->create([
                    'description' => (string) $row['description'],
                    'price' => (float) $row['price'],
                    'quantity' => (int) $row['quantity'],
                ]);
            }

            return $invoice;
        });

        Notification::make()
            ->title('Invoice created')
            ->body('Invoice #' . $invoice->id . ' for ' . number_format($invoice->items->sum(fn ($i) => $i->price * $i->quantity), 2) . ' ' . $invoice->currency_code . '.')
            ->success()->send();

        $this->redirect(EditInvoice::getUrl(['record' => $invoice->id]));
    }

    protected function getViewData(): array
    {
        return [
            'clients' => User::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']),
            'currencies' => \App\Models\Currency::orderBy('code')->pluck('code')->all(),
        ];
    }
}
