<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Notifications\Notification;
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

    // The reference's comparator fields: an operator (gt | lt) and a number each, for
    // Visitors Referred, Balance and Withdrawn.
    #[Url]
    public string $vop = 'gt';

    #[Url]
    public string $vval = '';

    #[Url]
    public string $bop = 'gt';

    #[Url]
    public string $bval = '';

    #[Url]
    public string $wop = 'gt';

    #[Url]
    public string $wval = '';

    /** Issue #6: the reference's per-affiliate detail screen, opened by the list's edit icon. */
    #[Url]
    public ?int $affiliate = null;

    #[Url(as: 'detail')]
    public string $dtab = 'referrals';

    /** The detail form's editable fields — the extension's real columns. */
    public array $edit = ['reward' => '', 'discount' => '', 'visitors' => 0, 'commissionType' => 'default', 'oneTimeOnly' => false];

    /** The reference's Time Period strip on the Referrals tab: 30/60/90/180 days. */
    #[Url]
    public string $period = '30';

    /** The Make Withdrawal Payout form on the Withdrawals History tab (issue #6). */
    public array $withdraw = ['amount' => '', 'currency' => 'USD', 'type' => 'external', 'txid' => '', 'note' => ''];

    /** The reference's "Add Manual Commission Entry" form, Commissions History tab. */
    public array $manual = ['orderId' => '', 'description' => '', 'amount' => ''];

    public function openAffiliate(int $id): void
    {
        $this->affiliate = $id;
        $this->dtab = 'referrals';
        $this->loadEdit();
    }

    /** The row's delete icon — awaiting the "Are you sure?" modal, or null. */
    public ?int $confirmingDelete = null;

    public function confirmDelete(int $id): void
    {
        $this->confirmingDelete = $id;
    }

    /**
     * Deleting an affiliate cascades onto `ext_affiliate_orders` — their whole referred-order
     * and earnings history — so an affiliate that has actually referred something is refused,
     * not silently wiped. A row with nothing recorded against it (never referred an order)
     * deletes clean.
     */
    public function runDelete(): void
    {
        $id = $this->confirmingDelete;
        $this->confirmingDelete = null;

        $row = Affiliate::withCount('orders')->find($id);

        if (!$row) {
            return;
        }

        if ($row->orders_count > 0) {
            Notification::make()
                ->title('Cannot delete')
                ->body('This affiliate has referred orders — deleting it would delete their earnings history too.')
                ->danger()->send();

            return;
        }

        $row->delete();

        if ($this->affiliate === $id) {
            $this->affiliate = null;
        }

        Notification::make()->title('Affiliate deleted')->success()->send();
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
                // 'default' whenever reward is unset — that IS the reference's "Use
                // Default", not a third state to track separately.
                'commissionType' => $row->commission_type === 'percentage' && $row->reward ? 'percentage' : 'default',
                'oneTimeOnly' => (bool) $row->one_time_only,
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
            'edit.commissionType' => 'required|in:default,percentage',
        ], attributes: ['edit.reward' => 'commission', 'edit.discount' => 'discount', 'edit.visitors' => 'visitors referred', 'edit.commissionType' => 'commission type']);

        // "Use Default" means the override is gone, not merely unread — RewardAffiliate
        // and AffiliateOrder::earnings() both fall back to the extension's default_reward
        // only when this column is empty.
        $reward = $this->edit['commissionType'] === 'percentage'
            ? ($this->edit['reward'] === '' ? 0 : $this->edit['reward'])
            : null;

        $affiliate = Affiliate::findOrFail($this->affiliate);
        $affiliate->forceFill([
            'reward' => $reward,
            'discount' => $this->edit['discount'] === '' ? 0 : $this->edit['discount'],
            'visitors' => $this->edit['visitors'],
            'commission_type' => $this->edit['commissionType'],
            'one_time_only' => (bool) $this->edit['oneTimeOnly'],
        ])->save();

        \Filament\Notifications\Notification::make()->title('Affiliate saved')->success()->send();
    }

    /**
     * The reference's Add Manual Commission Entry, Commissions History tab. Recorded in
     * AdminOps's own ledger (issue #6's withdrawals table has the same shape) and counted
     * into {@see totalEarned()} alongside real referred-order earnings, so it is real
     * money the affiliate can withdraw rather than a decorative log line.
     */
    public function addManualCommission(): void
    {
        $this->validate([
            'manual.amount' => 'required|numeric|min:0.01',
            'manual.orderId' => 'nullable|integer',
            'manual.description' => 'nullable|string|max:255',
        ], attributes: ['manual.amount' => 'amount', 'manual.orderId' => 'related referral']);

        $affiliate = Affiliate::findOrFail($this->affiliate);

        // The dropdown only ever offers this affiliate's own referred orders — a stray id
        // is dropped rather than trusted, the same guard the config-option save uses.
        $orderId = $this->manual['orderId'] !== ''
            && $affiliate->orders()->where('order_id', (int) $this->manual['orderId'])->exists()
                ? (int) $this->manual['orderId']
                : null;

        \Paymenter\Extensions\Others\AdminOps\Models\AffiliateManualCommission::create([
            'affiliate_id' => $affiliate->id,
            'order_id' => $orderId,
            'description' => $this->manual['description'] ?: null,
            'amount' => (float) $this->manual['amount'],
            'currency_code' => strtoupper(config('settings.default_currency', 'USD')),
            'admin_id' => auth()->id(),
        ]);

        $this->manual = ['orderId' => '', 'description' => '', 'amount' => ''];

        \Filament\Notifications\Notification::make()->title('Commission entry added')->success()->send();
    }

    /**
     * Everything this affiliate has actually earned, by currency: real referred-order
     * commissions plus manual entries. The one total {@see balance()} and the Available
     * to Withdraw row both read, so a manual entry changes both consistently.
     *
     * @return array<string, float>
     */
    public static function totalEarned(Affiliate $affiliate): array
    {
        $earnings = array_filter($affiliate->earnings);

        foreach (\Paymenter\Extensions\Others\AdminOps\Models\AffiliateManualCommission::query()
            ->where('affiliate_id', $affiliate->id)
            ->selectRaw('currency_code, sum(amount) as total')
            ->groupBy('currency_code')
            ->pluck('total', 'currency_code') as $currency => $amount) {
            $earnings[$currency] = ($earnings[$currency] ?? 0) + (float) $amount;
        }

        return $earnings;
    }

    /**
     * "$18.90 USD" of earned-minus-already-withdrawn, or "$0.00 USD" — the reference's
     * Available to Withdraw Balance row. Computed rather than a raw editable number
     * (which is what the reference itself shows) because nothing here would make typing a
     * bigger number actually payable — the real balance is what orders and manual
     * entries actually earned, less what {@see recordWithdrawal()} already paid out.
     */
    public static function availableToWithdraw(Affiliate $affiliate): string
    {
        $earned = self::totalEarned($affiliate);
        $paid = self::withdrawalsReady()
            ? \Paymenter\Extensions\Others\AdminOps\Models\AffiliateWithdrawal::query()
                ->where('affiliate_id', $affiliate->id)
                ->selectRaw('currency_code, sum(amount) as total')
                ->groupBy('currency_code')
                ->pluck('total', 'currency_code')->all()
            : [];

        $net = [];
        foreach ($earned as $currency => $amount) {
            $left = $amount - (float) ($paid[$currency] ?? 0);
            if (abs($left) > 0.004) {
                $net[$currency] = $left;
            }
        }

        if ($net === []) {
            return '$0.00 USD';
        }

        return implode(' · ', array_map(
            fn ($total, $currency): string => '$' . number_format(max(0, (float) $total), 2) . ' ' . $currency,
            $net,
            array_keys($net),
        ));
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
            'withdraw.type' => 'required|in:external,credit',
            'withdraw.txid' => 'nullable|string|max:255',
            'withdraw.note' => 'nullable|string|max:255',
        ], attributes: ['withdraw.amount' => 'amount', 'withdraw.currency' => 'currency', 'withdraw.type' => 'payout type', 'withdraw.txid' => 'transaction ID', 'withdraw.note' => 'note']);

        $affiliate = Affiliate::findOrFail($this->affiliate);

        // A payout can never exceed what the affiliate has actually earned in that
        // currency, minus what was already paid out — the ledger must not go negative.
        $currency = strtoupper($this->withdraw['currency']);
        $earned = (float) (self::totalEarned($affiliate)[$currency] ?? 0);
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

        // The reference's Payout Type. "Add to client's credit balance" is this store's
        // real equivalent of WHMCS's Create Transaction to Client — the affiliate IS a
        // client, and credit is money they can spend here. External stays a record of a
        // payout made outside the panel (bank, PIX).
        if ($this->withdraw['type'] === 'credit') {
            $credit = \App\Models\Credit::firstOrCreate(
                ['user_id' => $affiliate->user_id, 'currency_code' => $currency],
                ['amount' => 0],
            );
            $credit->increment('amount', $amount);
        }

        $noteParts = array_filter([
            $this->withdraw['type'] === 'credit' ? 'Paid to client credit balance' : null,
            $this->withdraw['txid'] !== '' ? 'Transaction: ' . $this->withdraw['txid'] : null,
            $this->withdraw['note'] ?: null,
        ]);

        \Paymenter\Extensions\Others\AdminOps\Models\AffiliateWithdrawal::create([
            'affiliate_id' => $affiliate->id,
            'amount' => $amount,
            'currency_code' => $currency,
            'note' => implode(' · ', $noteParts) ?: null,
            'admin_id' => auth()->id(),
        ]);

        $wasCredit = $this->withdraw['type'] === 'credit';
        $this->withdraw = ['amount' => '', 'currency' => $currency, 'type' => 'external', 'txid' => '', 'note' => ''];

        \Filament\Notifications\Notification::make()->title('Withdrawal recorded')
            ->body($wasCredit ? 'The amount is on the client\'s credit balance.' : null)
            ->success()->send();
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

        $query = Affiliate::query()
            ->with(['user', 'orders'])
            ->join('users', 'users.id', '=', 'ext_affiliates.user_id')
            ->when($this->q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('users.first_name', 'like', '%' . $this->q . '%')
                ->orWhere('users.last_name', 'like', '%' . $this->q . '%')
                ->orWhere('users.email', 'like', '%' . $this->q . '%')))
            ->when(is_numeric($this->vval), fn ($query) => $query
                ->where('ext_affiliates.visitors', $this->vop === 'lt' ? '<' : '>', (int) $this->vval))
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->select('ext_affiliates.*');

        // Balance and Withdrawn are derived (earnings per currency, payout sums), so those
        // comparators filter the SQL-narrowed set here and page by hand — the same pattern
        // Manage Orders uses for its derived columns.
        if (!is_numeric($this->bval) && !is_numeric($this->wval)) {
            return ['affiliates' => $query->paginate(self::PER_PAGE, page: $this->page), 'current' => null];
        }

        $compare = fn (float $actual, string $op, string $wanted): bool => $op === 'lt'
            ? $actual < (float) $wanted
            : $actual > (float) $wanted;

        $all = $query->get()
            ->filter(fn (Affiliate $row): bool => !is_numeric($this->bval)
                || $compare((float) array_sum(self::totalEarned($row)), $this->bop, $this->bval))
            ->filter(fn (Affiliate $row): bool => !is_numeric($this->wval)
                || $compare(self::withdrawnTotal($row), $this->wop, $this->wval))
            ->values();

        return [
            'affiliates' => new \Illuminate\Pagination\LengthAwarePaginator(
                $all->forPage($this->page, self::PER_PAGE)->values(),
                $all->count(),
                self::PER_PAGE,
                $this->page,
            ),
            'current' => null,
        ];
    }

    /** The recorded payouts as one number, currencies summed — the comparator's view of them. */
    private static function withdrawnTotal(Affiliate $affiliate): float
    {
        if (!self::withdrawalsReady()) {
            return 0.0;
        }

        return (float) \Paymenter\Extensions\Others\AdminOps\Models\AffiliateWithdrawal::query()
            ->where('affiliate_id', $affiliate->id)->sum('amount');
    }

    /** "$18.90 USD", or "$0.00 USD" for an affiliate whose referrals have paid nothing. */
    public static function balance(Affiliate $affiliate): string
    {
        $earnings = self::totalEarned($affiliate);

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
