<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\Quotes\Models\Quote;

/**
 * WHMCS's Quotes list, read from the live reference: ID, Subject, Client Name, Stage,
 * Total, Valid Until, Last Modified, sorted newest-modified first, with the Valid and
 * Expired views its sidebar links to. Writing a quote stays on {@see CreateQuote}.
 */
class QuotesList extends Page
{
    protected string $view = 'adminops::pages.quotes-list';

    protected static ?string $slug = 'manage-quotes';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'view')]
    public string $tab = 'all';

    /** The reference's Search/Filter band — every list page carries one; this one did not. */
    public bool $filter = false;

    #[Url]
    public string $q = '';

    #[Url]
    public string $stage = '';

    public static function canAccess(): bool
    {
        return class_exists(Quote::class)
            && (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Quotes';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    protected function getViewData(): array
    {
        $quotes = Quote::with(['user', 'items'])
            ->when($this->tab === 'valid', fn ($q) => $q->whereIn('status', [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
                ->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString())))
            ->when($this->tab === 'expired', fn ($q) => $q->where(fn ($w) => $w->where('status', Quote::STATUS_EXPIRED)
                ->orWhere(fn ($e) => $e->whereIn('status', [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
                    ->whereNotNull('valid_until')->where('valid_until', '<', now()->toDateString()))))
            ->when($this->stage !== '', fn ($q) => $q->where('status', $this->stage))
            ->when(trim($this->q) !== '', function ($query) {
                $needle = trim($this->q);
                $query->where(function ($w) use ($needle) {
                    $w->where('subject', 'like', "%{$needle}%")
                        ->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$needle}%")
                            ->orWhere('last_name', 'like', "%{$needle}%")
                            ->orWhere('email', 'like', "%{$needle}%"));
                });
            })
            ->latest('updated_at')->limit(200)->get();

        return ['quotes' => $quotes, 'stages' => CreateQuote::STAGES];
    }
}
