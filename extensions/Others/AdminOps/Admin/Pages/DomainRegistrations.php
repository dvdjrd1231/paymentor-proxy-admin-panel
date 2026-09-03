<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ServiceResource;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * WHMCS's Domain Registrations, to its screenshot: the Search/Filter panel (Domain,
 * Registrar, Status, Client Name), the records line with Jump to Page, and the navy grid —
 * ID, Domain, Client Name, Reg Period, Registrar, Price, Next Due Date, Expiry Date,
 * Status.
 *
 * This store registers no domains and Paymenter holds no domain table, so the list is
 * honestly empty: the reference's own "No Records Found", never an invented row. The page
 * exists because the reference's sidebar lists it — the earlier dead label in the rail
 * read as a rendering fault, where the reference's real-but-empty screen reads as the
 * truth about this business.
 */
class DomainRegistrations extends Page
{
    protected string $view = 'adminops::pages.domain-registrations';

    protected static ?string $slug = 'domain-registrations';

    /** Navigation is built by {@see \Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public bool $filter = false;

    /** Every filter is a URL, as on every other list — searching an empty set stays empty. */
    #[Url]
    public string $domain = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $registrar = '';

    #[Url]
    public string $client = '';

    #[Url]
    public bool $hideInactive = true;

    /** The reference's own Status options for this screen. */
    public const STATUSES = [
        'active' => 'Active',
        'pending' => 'Pending',
        'pending_transfer' => 'Pending Transfer',
        'grace' => 'Grace',
        'expired' => 'Expired',
        'transferred_away' => 'Transferred Away',
        'cancelled' => 'Cancelled',
        'fraud' => 'Fraud',
    ];

    public static function canAccess(): bool
    {
        return ServiceResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Domain Registrations';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function toggleInactive(): void
    {
        $this->hideInactive = !$this->hideInactive;
    }

    /** The band's Search button: the props are the whole state, so there is nothing else to do. */
    public function search(): void
    {
    }
}
