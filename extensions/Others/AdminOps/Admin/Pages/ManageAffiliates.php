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

        return ['affiliates' => $affiliates];
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
