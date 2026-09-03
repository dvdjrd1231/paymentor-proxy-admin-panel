<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\Affiliates\Admin\Resources\AffiliateResource;
use Paymenter\Extensions\Others\Affiliates\Models\Affiliate;

/**
 * WHMCS's Affiliates list, to its screenshot: ID, Signup Date, Client Name, Visitors
 * Referred, Signups, Balance, Withdrawn — sorted by client name as the reference's ▲ shows.
 *
 * Balance is the affiliate's earnings, summed per currency from their referred orders.
 * Withdrawn is an em dash: Paymenter's affiliate extension keeps no withdrawal ledger, and a
 * column that always said $0.00 would be claiming an answer nobody recorded. The column is
 * kept so the grid reads as the reference's; the dash is the honest cell.
 */
class ManageAffiliates extends Page
{
    protected string $view = 'adminops::pages.manage-affiliates';

    /** Not `affiliates`: that slug is the AffiliateResource's, and the resource wins it. */
    protected static ?string $slug = 'manage-affiliates';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 100;

    #[Url]
    public int $page = 1;

    #[Url]
    public bool $filter = false;

    #[Url]
    public string $q = '';

    /** Issue #6: the reference's per-affiliate detail screen, opened by the list's edit icon. */
    #[Url]
    public ?int $affiliate = null;

    #[Url(as: 'detail')]
    public string $dtab = 'referrals';

    /** The detail form's editable fields — the extension's real columns. */
    public array $edit = ['reward' => '', 'discount' => '', 'visitors' => 0];

    /** The Record Withdrawal form on the Withdrawals History tab (issue #6). */
    public array $withdraw = ['amount' => '', 'currency' => 'USD', 'note' => ''];

    public function openAffiliate(int $id): void
    {
        $this->affiliate = $id;
        $this->dtab = 'referrals';
        $this->loadEdit();
    }

    public function mount(): void
    {
        if ($this->affiliate) {
            $this->loadEdit();
        }
    }

    private function loadEdit(): void
    {
        if ($row = Affiliate::find($this->affiliate)) {
            $this->edit = [
                'reward' => (string) $row->reward,
                'discount' => (string) $row->discount,
                'visitors' => (int) $row->visitors,
            ];
        }
    }

    public function saveAffiliate(): void
    {
        // Nullable on purpose: a live affiliate row can carry NULL discount/reward, and
        // an empty box means "none" — required-validation locked such rows out of saving.
        $this->validate([
            'edit.reward' => 'nullable|numeric|min:0|max:100',
            'edit.discount' => 'nullable|numeric|min:0|max:100',
            'edit.visitors' => 'required|integer|min:0',
        ], attributes: ['edit.reward' => 'commission', 'edit.discount' => 'discount', 'edit.visitors' => 'visitors referred']);

        Affiliate::findOrFail($this->affiliate)->update([
            'reward' => $this->edit['reward'] === '' ? 0 : $this->edit['reward'],
            'discount' => $this->edit['discount'] === '' ? 0 : $this->edit['discount'],
            'visitors' => $this->edit['visitors'],
        ]);

        \Filament\Notifications\Notification::make()->title('Affiliate saved')->success()->send();
    }

    /**
     * Issue #6's "How are withdrawals processed?": the payout happens outside the panel —
     * bank transfer, PIX, or account credit — and is recorded here, which is the
     * reference's own model. The ledger is AdminOps's table; the affiliate extension
     * stays untouched.
     */
    public function recordWithdrawal(): void
    {
        if (!self::withdrawalsReady()) {
            \Filament\Notifications\Notification::make()
                ->title('The withdrawals table has not been migrated yet')->danger()->send();

            return;
        }

        $this->validate([
            'withdraw.amount' => 'required|numeric|min:0.01',
            'withdraw.currency' => 'required|string|size:3',
            'withdraw.note' => 'nullable|string|max:255',
        ], attributes: ['withdraw.amount' => 'amount', 'withdraw.currency' => 'currency', 'withdraw.note' => 'note']);

        $affiliate = Affiliate::findOrFail($this->affiliate);

        // A payout can never exceed what the affiliate has actually earned in that
        // currency, minus what was already paid out — the ledger must not go negative.
        $currency = strtoupper($this->withdraw['currency']);
        $earned = (float) (array_filter($affiliate->earnings)[$currency] ?? 0);
        $paid = (float) \Paymenter\Extensions\Others\AdminOps\Models\AffiliateWithdrawal::query()
            ->where('affiliate_id', $affiliate->id)->where('currency_code', $currency)->sum('amount');
        $amount = (float) $this->withdraw['amount'];

        if ($amount > $earned - $paid + 0.005) {
            $this->addError('withdraw.amount', sprintf(
                'Only $%s %s is available to withdraw (earned $%s, already withdrawn $%s).',
                number_format($earned - $paid, 2), $currency, number_format($earned, 2), number_format($paid, 2),
            ));

            return;
        }

        \Paymenter\Extensions\Others\AdminOps\Models\AffiliateWithdrawal::create([
            'affiliate_id' => $affiliate->id,
            'amount' => $amount,
            'currency_code' => $currency,
            'note' => $this->withdraw['note'] ?: null,
            'admin_id' => auth()->id(),
        ]);

        $this->withdraw = ['amount' => '', 'currency' => $currency, 'note' => ''];

        \Filament\Notifications\Notification::make()->title('Withdrawal recorded')->success()->send();
    }

    /** Enabled-but-unmigrated installs fall back to the honest empty message. */
    public static function withdrawalsReady(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('ext_affiliate_withdrawals');
        } catch (\Throwable) {
            return false;
        }
    }

    /** "$5.00 USD" of recorded payouts, or "$0.00 USD" — the list's Withdrawn column. */
    public static function withdrawn(Affiliate $affiliate): string
    {
        if (!self::withdrawalsReady()) {
            return '$0.00 USD';
        }

        $sums = \Paymenter\Extensions\Others\AdminOps\Models\AffiliateWithdrawal::query()
            ->where('affiliate_id', $affiliate->id)
            ->selectRaw('currency_code, sum(amount) as total')
            ->groupBy('currency_code')
            ->pluck('total', 'currency_code')
            ->all();

        if ($sums === []) {
            return '$0.00 USD';
        }

        return implode(' · ', array_map(
            fn ($total, $currency): string => '$' . number_format((float) $total, 2) . ' ' . $currency,
            $sums,
            array_keys($sums),
        ));
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function search(): void
    {
        $this->page = 1;
    }

    public static function canAccess(): bool
    {
        return class_exists(AffiliateResource::class) && AffiliateResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Affiliates';
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    protected function getViewData(): array
    {
        if ($this->affiliate) {
            $current = Affiliate::with(['user', 'orders.order.user', 'orders.order.services.product'])->find($this->affiliate);

            if ($current) {
                return ['current' => $current, 'affiliates' => null];
            }

            $this->affiliate = null;
        }

        $affiliates = Affiliate::query()
            ->with(['user', 'orders'])
            ->join('users', 'users.id', '=', 'ext_affiliates.user_id')
            ->when($this->q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('users.first_name', 'like', '%' . $this->q . '%')
                ->orWhere('users.last_name', 'like', '%' . $this->q . '%')
                ->orWhere('users.email', 'like', '%' . $this->q . '%')))
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->select('ext_affiliates.*')
            ->paginate(self::PER_PAGE, page: $this->page);

        return ['affiliates' => $affiliates, 'current' => null];
    }

    /** "$18.90 USD", or "$0.00 USD" for an affiliate whose referrals have paid nothing. */
    public static function balance(Affiliate $affiliate): string
    {
        $earnings = array_filter($affiliate->earnings);

        if ($earnings === []) {
            return '$0.00 USD';
        }

        return implode(' · ', array_map(
            fn ($total, $currency): string => '$' . number_format((float) $total, 2) . ' ' . $currency,
            $earnings,
            array_keys($earnings),
        ));
    }
}
