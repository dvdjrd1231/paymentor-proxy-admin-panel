<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\Invoice;
use App\Models\Service;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Calendar, with the dates this store actually has: services falling due and
 * unpaid invoices' due dates, on a month grid with previous/next.
 */
class UtilitiesCalendar extends Page
{
    protected string $view = 'adminops::pages.utilities-calendar';

    protected static ?string $slug = 'calendar';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The showing month, YYYY-MM. */
    #[Url]
    public string $ym = '';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Calendar';
    }

    public function mount(): void
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $this->ym)) {
            $this->ym = now()->format('Y-m');
        }
    }

    public function move(int $months): void
    {
        $this->ym = Carbon::createFromFormat('Y-m', $this->ym)->addMonths($months)->format('Y-m');
    }

    protected function getViewData(): array
    {
        $month = Carbon::createFromFormat('Y-m', $this->ym)->startOfMonth();
        $events = [];

        foreach (Service::whereBetween('expires_at', [$month, $month->copy()->endOfMonth()])
            ->with('product')->get() as $service) {
            $events[(int) $service->expires_at->format('j')][] =
                ['label' => 'Due: ' . ($service->product?->name ?? 'Service') . ' #' . $service->id, 'kind' => 'service'];
        }

        foreach (Invoice::where('status', 'pending')
            ->whereBetween('due_at', [$month, $month->copy()->endOfMonth()])->get() as $invoice) {
            $events[(int) Carbon::parse($invoice->due_at)->format('j')][] =
                ['label' => 'Invoice ' . ($invoice->number ?? '#' . $invoice->id), 'kind' => 'invoice'];
        }

        return [
            'month' => $month,
            'events' => $events,
            // The grid starts on Sunday, as the reference's calendar does.
            'lead' => (int) $month->copy()->startOfMonth()->format('w'),
            'days' => (int) $month->daysInMonth,
        ];
    }
}
