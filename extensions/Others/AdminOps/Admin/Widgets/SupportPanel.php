<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Models\Ticket;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\OpenNewTicket;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportTickets;

/**
 * Issue #37 — WHMCS's Support panel: Awaiting Reply and Assigned To You, the latest
 * tickets with how long ago they moved, and the three links underneath.
 */
class SupportPanel extends Widget
{
    protected string $view = 'adminops::widgets.support-panel';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.widgets.overview');
    }

    protected function getViewData(): array
    {
        $urls = ['all' => null, 'mine' => null, 'open' => null];

        try {
            $urls = [
                'all' => SupportTickets::getUrl(),
                'mine' => SupportTickets::getUrl(['assigned' => Auth::id()]),
                'open' => OpenNewTicket::getUrl(),
            ];
        } catch (\Throwable $e) {
        }

        return [
            'awaiting' => Ticket::where('status', 'open')->count(),
            'mine' => Ticket::where('assigned_to', Auth::id())->where('status', '!=', 'closed')->count(),
            'recent' => Ticket::latest('updated_at')->limit(5)->get(['id', 'subject', 'updated_at']),
            'urls' => $urls,
        ];
    }
}
