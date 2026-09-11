<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\BillingAgreement;
use App\Models\Invoice;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #15 — WHMCS's Offline Credit Card Processing: the queue of invoices belonging to a
 * client whose payment method is a card kept on file for a *manual* charge attempt, rather
 * than one a gateway bills automatically.
 */
class OfflineCcProcessing extends Page
{
    protected string $view = 'adminops::pages.offline-cc-processing';

    protected static ?string $slug = 'offline-cc-processing';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 100;

    #[Url]
    public int $page = 1;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Offline Credit Card Processing';
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    protected function getViewData(): array
    {
        // BillingAgreement is soft-deleted, so a removed card drops its client out of the
        // queue on its own — no explicit deleted_at check needed here.
        $cardholders = BillingAgreement::query()->pluck('user_id')->unique();

        $invoices = Invoice::with('user')
            ->where('status', Invoice::STATUS_PENDING)
            ->whereIn('user_id', $cardholders)
            ->orderBy('due_at')
            ->paginate(self::PER_PAGE, page: $this->page);

        return ['invoices' => $invoices];
    }
}
