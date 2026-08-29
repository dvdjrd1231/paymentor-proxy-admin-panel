<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\Currency;
use App\Models\CustomProperty;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\Quotes\Models\Quote;
use Paymenter\Extensions\Others\Quotes\Support\Quoting;

/**
 * WHMCS's Create New Quote, to its screenshots: General Information (subject, stage, dates),
 * the button row, Client Information (existing client or a new one typed in place), the
 * Line Items grid with per-line discount and taxed flag, and the three notes boxes.
 *
 * One page for create and edit — the reference's own behaviour: Save on an empty form
 * creates the quote and stays on it, every later Save updates it. The stage select speaks
 * the reference's words (Draft/Delivered/…) over the extension's real statuses.
 *
 * "Quote for new client" creates a real client account — a quote must belong to someone
 * the system knows, so the lead becomes a user with a random password they can reset.
 */
class CreateQuote extends Page
{
    protected string $view = 'adminops::pages.create-quote';

    protected static ?string $slug = 'create-quote';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?Quote $quote = null;

    public string $subject = '';

    /** The extension's real status, shown through the reference's stage words. */
    public string $stage = Quote::STATUS_DRAFT;

    public ?string $validUntil = null;

    public string $clientMode = 'existing';

    public ?int $userId = null;

    /** @var array<string, string> The "Quote for new client" block. */
    public array $nc = [
        'first_name' => '', 'last_name' => '', 'company_name' => '', 'email' => '',
        'phone' => '', 'currency' => '', 'address' => '', 'address2' => '',
        'city' => '', 'state' => '', 'zip' => '', 'country' => '',
    ];

    /** @var array<int, array{quantity: int|string, description: string, price: string, discount: string, taxed: bool}> */
    public array $items = [];

    public string $proposal = '';

    public string $customerNotes = '';

    public string $adminNotes = '';

    /** True while the delete "Are you sure?" is showing. */
    public bool $confirmingDelete = false;

    public const STAGES = [
        Quote::STATUS_DRAFT => 'Draft',
        Quote::STATUS_SENT => 'Delivered',
        Quote::STATUS_ACCEPTED => 'Accepted',
        Quote::STATUS_DECLINED => 'Lost',
        Quote::STATUS_EXPIRED => 'Dead',
    ];

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record?}';
    }

    public static function canAccess(): bool
    {
        return class_exists(Quote::class)
            && (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Quotes';
    }

    public function mount(int|string|null $record = null): void
    {
        if ($record === null) {
            $this->items = [self::blankItem()];
            $this->validUntil = now()->addMonth()->format('Y-m-d');

            return;
        }

        $this->quote = Quote::with(['items', 'user'])->findOrFail($record);
        $this->subject = $this->quote->subject;
        $this->stage = $this->quote->status;
        $this->validUntil = $this->quote->valid_until?->format('Y-m-d');
        $this->userId = $this->quote->user_id;
        $this->proposal = (string) $this->quote->proposal_text;
        $this->customerNotes = (string) $this->quote->customer_notes;
        $this->adminNotes = (string) $this->quote->notes;

        $this->items = $this->quote->items->map(fn ($item): array => [
            'quantity' => (int) $item->quantity,
            'description' => $item->description,
            'price' => number_format((float) $item->price, 2, '.', ''),
            'discount' => number_format((float) ($item->discount ?? 0), 2, '.', ''),
            'taxed' => (bool) ($item->taxed ?? false),
        ])->values()->all() ?: [self::blankItem()];
    }

    private static function blankItem(): array
    {
        return ['quantity' => 1, 'description' => '', 'price' => '0.00', 'discount' => '0.00', 'taxed' => false];
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

    /** The reference's "Add a Predefined Product": a line from the catalogue, priced. */
    public function addProduct(int $productId): void
    {
        $product = Product::with('category')->findOrFail($productId);
        $plan = Plan::where('priceable_type', Product::class)->where('priceable_id', $productId)->first();
        $currency = config('settings.default_currency', 'USD');

        $this->items[] = [
            'quantity' => 1,
            'description' => trim(($product->category?->name ? $product->category->name . ' - ' : '') . $product->name),
            'price' => number_format((float) ($plan?->price($currency)->price ?? 0), 2, '.', ''),
            'discount' => '0.00',
            'taxed' => false,
        ];

        // A blank first row is scaffolding, not a line; a picked product replaces it.
        if (count($this->items) > 1 && trim($this->items[0]['description']) === '' && (float) $this->items[0]['price'] === 0.0) {
            array_shift($this->items);
        }
    }

    public function lineTotal(array $item): float
    {
        return round(max(1, (int) $item['quantity'])
            * (float) ($item['price'] ?: 0)
            * (1 - min(100, max(0, (float) ($item['discount'] ?: 0))) / 100), 2);
    }

    public function subTotal(): float
    {
        return round(array_sum(array_map(fn ($item) => $this->lineTotal($item), $this->items)), 2);
    }

    public function save(): void
    {
        $rules = [
            'subject' => 'required|string|max:255',
            'stage' => Rule::in(array_keys(self::STAGES)),
            'validUntil' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
        ];

        if ($this->clientMode === 'existing' || $this->quote) {
            $rules['userId'] = 'required|exists:users,id';
        } else {
            $rules['nc.first_name'] = 'required|string|max:255';
            $rules['nc.last_name'] = 'required|string|max:255';
            $rules['nc.email'] = ['required', 'email', Rule::unique('users', 'email')];
        }

        $this->validate($rules, attributes: [
            'userId' => 'client', 'nc.first_name' => 'first name',
            'nc.last_name' => 'last name', 'nc.email' => 'email address',
        ]);

        $quote = DB::transaction(function (): Quote {
            $user = $this->resolveUser();

            $values = [
                'user_id' => $user->id,
                'subject' => $this->subject,
                'status' => $this->stage,
                'currency_code' => $this->nc['currency'] ?: ($this->quote->currency_code ?? config('settings.default_currency', 'USD')),
                'valid_until' => $this->validUntil ?: null,
                'proposal_text' => $this->proposal ?: null,
                'customer_notes' => $this->customerNotes ?: null,
                'notes' => $this->adminNotes ?: null,
            ];

            if ($this->quote) {
                $this->quote->update($values);
                $quote = $this->quote;
            } else {
                $quote = Quote::create($values + ['admin_id' => Auth::id()]);
            }

            // Rewritten wholesale: nothing else references quote item rows, so replacing
            // them is simpler and safer than diffing.
            $quote->items()->delete();
            foreach (array_values($this->items) as $sort => $item) {
                if (trim($item['description']) === '') {
                    continue;
                }

                $quote->items()->create([
                    'description' => $item['description'],
                    'price' => (float) $item['price'],
                    'quantity' => max(1, (int) $item['quantity']),
                    'discount' => min(100, max(0, (float) ($item['discount'] ?: 0))),
                    'taxed' => (bool) $item['taxed'],
                    'sort' => $sort,
                ]);
            }

            return $quote;
        });

        Notification::make()->title('Quote saved')->success()->send();

        if (!$this->quote) {
            $this->redirect(static::getUrl(['record' => $quote->id]));
        } else {
            $this->mount($quote->id);
        }
    }

    /** The picked client, or the typed-in one made real. */
    private function resolveUser(): User
    {
        if ($this->clientMode === 'existing' || $this->quote) {
            return User::whereNull('role_id')->findOrFail($this->userId);
        }

        $user = User::create([
            'first_name' => $this->nc['first_name'],
            'last_name' => $this->nc['last_name'],
            'email' => $this->nc['email'],
            // A lead, not a login: random password, reset when they become a customer.
            'password' => Hash::make(Str::password(32)),
        ]);

        $keys = ['company_name', 'phone', 'address', 'address2', 'city', 'state', 'zip', 'country', 'currency'];
        $known = CustomProperty::where('model', User::class)->pluck('key')->all();
        foreach ($keys as $key) {
            $value = trim((string) ($this->nc[$key] ?? ''));
            if ($value !== '' && (in_array($key, $known, true) || $key === 'currency')) {
                $user->properties()->create(['key' => $key, 'value' => $value]);
            }
        }

        $this->userId = $user->id;
        $this->clientMode = 'existing';

        return $user;
    }

    /** The reference's Duplicate: same quote, fresh draft, opened for editing. */
    public function duplicate(): void
    {
        if (!$this->quote) {
            return;
        }

        $copy = DB::transaction(function (): Quote {
            $copy = $this->quote->replicate(['status', 'sent_at', 'accepted_at', 'declined_at', 'invoice_id']);
            $copy->status = Quote::STATUS_DRAFT;
            $copy->admin_id = Auth::id();
            $copy->save();

            foreach ($this->quote->items as $item) {
                $copy->items()->create($item->only(['description', 'price', 'quantity', 'discount', 'taxed', 'sort']));
            }

            return $copy;
        });

        Notification::make()->title('Quote duplicated')->success()->send();
        $this->redirect(static::getUrl(['record' => $copy->id]));
    }

    /** The reference's Email as PDF: the quote email, through the extension's own gate. */
    public function emailQuote(): void
    {
        if (!$this->quote) {
            return;
        }

        if (Quoting::send($this->quote->refresh())) {
            Notification::make()->title('Quote emailed')->body($this->quote->user->email)->success()->send();
            $this->mount($this->quote->id);
        } else {
            Notification::make()->title('Not sent')
                ->body('Only draft quotes can be sent — this one is ' . $this->quote->status . '.')
                ->warning()->send();
        }
    }

    /** The reference's Convert to Invoice — once, guarded by the invoice link itself. */
    public function convertToInvoice(): void
    {
        if (!$this->quote || $this->quote->invoice_id) {
            Notification::make()->title('Already invoiced')->warning()->send();

            return;
        }

        $invoice = DB::transaction(function (): Invoice {
            $invoice = Invoice::create([
                'user_id' => $this->quote->user_id,
                'status' => Invoice::STATUS_PENDING,
                'currency_code' => $this->quote->currency_code,
                'due_at' => now()->addDays((int) config('settings.cronjob_invoice', 7)),
            ]);

            foreach ($this->quote->items as $item) {
                $invoice->items()->create([
                    // The discount is applied here, so the invoice charges what was quoted.
                    'price' => round((float) $item->price * (1 - (float) ($item->discount ?? 0) / 100), 2),
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                ]);
            }

            $this->quote->update([
                'status' => Quote::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });

        Notification::make()->title('Invoice #' . ($invoice->number ?? $invoice->id) . ' created')->success()->send();
        $this->mount($this->quote->id);
    }

    public function deleteQuote(): void
    {
        $this->confirmingDelete = false;

        if (!$this->quote) {
            return;
        }

        if ($this->quote->invoice_id) {
            Notification::make()->title('Cannot delete')
                ->body('This quote became an invoice — the record stays.')
                ->danger()->send();

            return;
        }

        DB::transaction(function (): void {
            $this->quote->items()->delete();
            $this->quote->delete();
        });

        Notification::make()->title('Quote deleted')->success()->send();
        $this->redirect(\Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource::getUrl('index'));
    }

    protected function getViewData(): array
    {
        $country = CustomProperty::where('model', User::class)->where('key', 'country')->first();

        return [
            'clients' => User::whereNull('role_id')->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'email']),
            'products' => Product::with('category')->orderBy('name')->get(['id', 'name', 'category_id']),
            'currencies' => Currency::pluck('code')->all(),
            'countries' => array_values((array) ($country?->allowed_values ?? [])),
            'pdfUrl' => $this->quote ? url('/admin/quote-pdf/' . $this->quote->id) : null,
        ];
    }
}
