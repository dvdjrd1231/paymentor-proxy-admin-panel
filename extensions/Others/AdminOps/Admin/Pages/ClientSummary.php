<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use App\Enums\InvoiceTransactionStatus;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Money;

/**
 * WHMCS's client summary: one customer, one screen.
 *
 * Paymenter already holds everything shown here, but spread over six sub-pages — profile,
 * services, invoices, credits, tickets, billing agreements — so answering "who is this and
 * what is going on with them", which is the first thing support does on every ticket, costs
 * five page loads. This is that answer on one page, with the actions that usually follow it
 * in the header.
 *
 * Read-only by design: everything editable stays on the core pages that own it, so there is
 * one place a change can be made and one set of validation rules to trust.
 *
 * @link docs/02b-admin-area.md
 */
class ClientSummary extends Page
{
    protected string $view = 'adminops::pages.client-summary';

    protected static ?string $slug = 'client-summary';

    /**
     * Reached from the customer list, never from the sidebar — a summary with no customer
     * chosen would have nothing to show.
     */
    protected static bool $shouldRegisterNavigation = false;

    /**
     * The customer being summarised.
     *
     * Deliberately *not* called `$record`: Livewire assigns route parameters to public
     * properties of the same name before `mount()` runs, so a `public User $record` would
     * be handed the raw `{record}` string from the URL and fail on the type before this
     * page ever got to resolve it. Public rather than protected so Livewire rehydrates it
     * from its key on the follow-up request that runs the impersonate action.
     */
    public User $customer;

    /** How many rows of each kind before "see all" takes over. */
    private const ROWS = 8;

    /**
     * The record is part of the path rather than the slug so the route keeps a clean name
     * (`…pages.client-summary`); putting `{record}` in `$slug` would bake the braces into
     * the route name, which `getUrl()` then has to match literally.
     */
    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public function mount(int|string $record): void
    {
        static::authorizeResourceAccess();

        $this->customer = User::query()
            ->with(['role', 'credits', 'properties.parent_property'])
            ->findOrFail($record);
    }

    public static function authorizeResourceAccess(): void
    {
        abort_unless(UserResource::canViewAny(), 403);
    }

    public function getTitle(): string
    {
        return $this->customer->name;
    }

    public function getSubheading(): ?string
    {
        return $this->customer->email;
    }

    protected function getHeaderActions(): array
    {
        return [
            // The supported way for an administrator to see a customer's account. Kept
            // identical to core's action on the user edit page — same session key, same
            // landing page — so there is one impersonation mechanism, not two.
            Action::make('impersonate')
                ->label('Log in as customer')
                ->icon(Heroicon::ArrowRightOnRectangle)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Log in as this customer?')
                ->modalDescription('You will browse the client area as them until you return to the admin panel.')
                ->action(function () {
                    session()->put('impersonating', $this->customer->id);

                    $this->redirect('/dashboard');
                })
                ->visible(fn (): bool => Auth::user()->hasPermission('admin.users.impersonate')
                    && Auth::id() !== $this->customer->id),

            Action::make('edit')
                ->label('Edit customer')
                ->icon(Heroicon::PencilSquare)
                ->color('gray')
                ->url(fn (): string => UserResource::getUrl('edit', ['record' => $this->customer]))
                ->visible(fn (): bool => UserResource::canEdit($this->customer)),

            Action::make('newInvoice')
                ->label('New invoice')
                ->icon(Heroicon::DocumentPlus)
                ->color('gray')
                ->url(fn (): string => InvoiceResource::getUrl('create'))
                ->visible(fn (): bool => InvoiceResource::canCreate()),
        ];
    }

    protected function getViewData(): array
    {
        $user = $this->customer;

        return [
            'user' => $user,
            'credits' => $user->credits->mapWithKeys(
                fn ($credit) => [$credit->currency_code => (float) $credit->amount]
            )->all(),
            'lifetime' => $this->lifetimeSpend(),
            'outstanding' => $this->outstanding(),
            'properties' => $user->properties
                ->filter(fn ($property) => filled($property->value) && $property->parent_property)
                ->mapWithKeys(fn ($property) => [$property->parent_property->name => $property->value])
                ->all(),
            'services' => $user->services()
                ->with('product')
                ->latest()
                ->limit(self::ROWS)
                ->get(),
            'serviceCount' => $user->services()->count(),
            'invoices' => $user->invoices()
                ->with(['items', 'transactions'])
                ->latest()
                ->limit(self::ROWS)
                ->get(),
            'invoiceCount' => $user->invoices()->count(),
            'tickets' => $user->tickets()
                ->latest()
                ->limit(self::ROWS)
                ->get(),
            'ticketCount' => $user->tickets()->count(),
            'urls' => [
                'services' => UserResource::getUrl('services', ['record' => $user]),
                'invoices' => UserResource::getUrl('invoices', ['record' => $user]),
                'tickets' => UserResource::getUrl('tickets', ['record' => $user]),
                'credits' => UserResource::getUrl('credits', ['record' => $user]),
                'service' => fn ($id) => ServiceResource::getUrl('edit', ['record' => $id]),
                'invoice' => fn ($id) => InvoiceResource::getUrl('edit', ['record' => $id]),
                'ticket' => fn ($id) => TicketResource::getUrl('edit', ['record' => $id]),
            ],
        ];
    }

    /**
     * Everything this customer has actually paid, by currency.
     *
     * Credit transactions are left out: settling an invoice from account credit spends
     * money that was already counted when the credit was bought, so including both would
     * report the customer as having paid twice.
     *
     * @return array<string, float>
     */
    private function lifetimeSpend(): array
    {
        return $this->customer->transactions()
            ->where('invoice_transactions.status', InvoiceTransactionStatus::Succeeded)
            ->where('invoice_transactions.is_credit_transaction', false)
            ->join('invoices as currency_source', 'currency_source.id', '=', 'invoice_transactions.invoice_id')
            ->groupBy('currency_source.currency_code')
            ->selectRaw('currency_source.currency_code as code, SUM(invoice_transactions.amount) as amount_sum')
            ->pluck('amount_sum', 'code')
            ->map(fn ($amount) => (float) $amount)
            ->all();
    }

    /**
     * What this customer still owes, by currency.
     *
     * Summed from the loaded invoices rather than in SQL, because an invoice total lives in
     * its items and `Invoice::$remaining` already nets off part payments.
     *
     * @return array<string, float>
     */
    private function outstanding(): array
    {
        $totals = [];

        $this->customer->invoices()
            ->where('status', Invoice::STATUS_PENDING)
            ->with(['items', 'transactions'])
            ->get()
            ->each(function (Invoice $invoice) use (&$totals) {
                $totals[$invoice->currency_code] = ($totals[$invoice->currency_code] ?? 0) + (float) $invoice->remaining;
            });

        return $totals;
    }

    public function formatMoney(float $amount, ?string $currency): string
    {
        return Money::format($amount, $currency);
    }

    public function formatTotals(array $totals): string
    {
        return Money::formatTotals($totals);
    }
}
