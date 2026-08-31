<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\InvoiceOps\Models\RefundRequest;

/**
 * Issue #16 — WHMCS's Disputes/Refund Requests as the navy list: who asked, for which
 * invoice, how much, why, and where it stands. Deciding a request stays on the InvoiceOps
 * resource, which owns the guarded approve/deny transitions; each row links straight there.
 */
class RefundRequests extends Page
{
    protected string $view = 'adminops::pages.refund-requests';

    protected static ?string $slug = 'disputes';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'view')]
    public string $tab = 'pending';

    public static function canAccess(): bool
    {
        return class_exists(RefundRequest::class)
            && (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Refund Requests';
    }

    protected function getViewData(): array
    {
        return [
            'rows' => RefundRequest::with(['user', 'invoice'])
                ->when($this->tab !== 'all', fn ($q) => $q->where('status', $this->tab))
                ->latest('id')->limit(200)->get(),
        ];
    }
}
