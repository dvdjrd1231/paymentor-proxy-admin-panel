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
 *
 * Paymenter has no "manual capture" flag on a gateway — every gateway extension here is a
 * live, webhook-driven processor (Stripe, CoinPayments, Cryptomus). What it does have is
 * `BillingAgreement`: a stored card ({@see \App\Models\BillingAgreement::$name}, "Visa ****
 * 4242") tied to one of those gateways. A client who has one *could* be charged by staff
 * running that gateway's own charge — the same action a manual attempt is — so that is this
 * queue: every pending invoice belonging to a client with a card on file, oldest due date
 * first, exactly as the reference sorts its own.
 *
 * Was Add Transaction's slot in the Billing menu; that page still exists, reached from a row
 * here (or directly) rather than from the menu, since it is the action this list points at,
 * not a second listing of the same thing.
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
