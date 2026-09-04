<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ServiceResource;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ServiceTermResource;
use Paymenter\Extensions\Others\TermLimits\Models\ServiceTerm;
use Paymenter\Extensions\Others\TermLimits\Support\Terms;

/**
 * Fixed Terms in the new window standard: the navy grid, Search/Filter, and the two
 * actions {@see ServiceTermResource} carried as plain Filament table actions — Extend
 * (a form modal: hours + a required reason) and History (the extensions already
 * granted) — as the reference's own form/list modals instead of Filament's action panel.
 *
 * There is no WHMCS screen this maps to; a fixed-term proxy is Paymenter's own concept
 * (see {@see \Paymenter\Extensions\Others\TermLimits\Support\Terms}). "The new window
 * standard" here means this project's own established shape for a list — the one every
 * other raw-Filament-resource page in this skin (Cancellation Requests, HTTP Log, OAuth
 * Clients) has already been carried to — not a reference screenshot, because none exists.
 */
class FixedTerms extends Page
{
    protected string $view = 'adminops::pages.fixed-terms';

    protected static ?string $slug = 'fixed-terms';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 100;

    /** The reference's Search/Filter panel: Service, Customer, Outcome. */
    #[Url]
    public bool $filter = false;

    #[Url]
    public string $q = '';

    #[Url]
    public string $outcome = '';

    /** "Still running" — WHMCS-style default-on filter chip, off shows every term. */
    #[Url]
    public bool $openOnly = true;

    #[Url]
    public int $page = 1;

    /** The term id whose Extend form is open, or null. */
    public ?int $extending = null;

    public array $extend = ['hours' => '', 'reason' => ''];

    /** The term id whose History modal is open, or null. */
    public ?int $viewingHistory = null;

    public static function canAccess(): bool
    {
        return ServiceTermResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Fixed Terms';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function toggleOpenOnly(): void
    {
        $this->openOnly = !$this->openOnly;
        $this->page = 1;
    }

    public function search(): void
    {
        $this->page = 1;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function openExtend(int $id): void
    {
        $this->extending = $id;
        $this->extend = ['hours' => '', 'reason' => ''];
    }

    public function closeExtend(): void
    {
        $this->reset('extending', 'extend');
    }

    public function saveExtend(): void
    {
        if (!ServiceTermResource::canExtend()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $term = ServiceTerm::find($this->extending);

        if (!$term) {
            $this->closeExtend();

            return;
        }

        $this->validate([
            'extend.hours' => 'required|integer|min:1|max:' . (24 * 30),
            'extend.reason' => 'required|string|min:10',
        ], attributes: ['extend.hours' => 'hours to add', 'extend.reason' => 'reason']);

        Terms::extend($term, (int) $this->extend['hours'], trim($this->extend['reason']), Auth::user());

        Notification::make()
            ->title('Term extended')
            ->body('Service #' . $term->service_id . ' now ends ' . $term->fresh()->ends_at->toDayDateTimeString() . '.')
            ->success()
            ->send();

        $this->closeExtend();
    }

    public function openHistory(int $id): void
    {
        $this->viewingHistory = $id;
    }

    public function closeHistory(): void
    {
        $this->reset('viewingHistory');
    }

    protected function getViewData(): array
    {
        $terms = ServiceTerm::query()
            // `extensions` eager-loaded so the grid's History icon (one per row) reads
            // the already-fetched collection rather than firing an exists() query apiece.
            ->with(['service.user', 'service.product', 'extensions'])
            ->when($this->openOnly, fn ($q) => $q->whereNull('ended_at'))
            ->when($this->outcome !== '', fn ($q) => $q->where('outcome', $this->outcome))
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('service_id', 'like', '%' . $this->q . '%')
                ->orWhereHas('service.user', fn ($u) => $u
                    ->where('email', 'like', '%' . $this->q . '%')
                    ->orWhere('first_name', 'like', '%' . $this->q . '%')
                    ->orWhere('last_name', 'like', '%' . $this->q . '%'))))
            ->orderBy('ends_at')
            ->paginate(self::PER_PAGE, page: $this->page);

        $history = null;
        if ($this->viewingHistory) {
            $term = ServiceTerm::with('extensions.admin')->find($this->viewingHistory);
            $history = $term?->extensions->sortByDesc('created_at');
        }

        return [
            'terms' => $terms,
            'canExtend' => ServiceTermResource::canExtend(),
            'history' => $history,
            'historyTerm' => $this->viewingHistory ? ServiceTerm::find($this->viewingHistory) : null,
        ];
    }

    /** The shared status-badge palette (ao-mu-st-*), same verdict the resource's own table used. */
    public static function remainingColor(ServiceTerm $term): string
    {
        return match (true) {
            !$term->isOpen() => 'ao-mu-st-suspended',
            $term->ends_at->isPast() => 'ao-mu-st-cancelled',
            $term->ends_at->diffInHours(now()) < 6 => 'ao-mu-st-pending',
            default => 'ao-mu-st-active',
        };
    }

    public static function serviceUrl(ServiceTerm $term): ?string
    {
        if (!$term->service) {
            return null;
        }

        try {
            return ClientSummary::serviceUrl($term->service);
        } catch (\Throwable) {
            return null;
        }
    }
}
