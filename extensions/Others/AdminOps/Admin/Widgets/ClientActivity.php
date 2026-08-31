<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Models\UserSession;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\Metrics;

/**
 * Issue #37 — WHMCS's Client Activity panel: Active Clients, Users Online in the last
 * hour, and the most recent client sign-ins with their IPs — clients, not staff, which is
 * what the reference shows and what the old admin-listing widget got wrong.
 */
class ClientActivity extends Widget
{
    protected string $view = 'adminops::widgets.client-activity';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.widgets.overview');
    }

    protected function getViewData(): array
    {
        $recent = collect();

        try {
            $recent = UserSession::query()
                ->whereHas('user', fn ($q) => $q->whereNull('role_id'))
                ->with('user')
                ->latest('last_activity')
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
        }

        return [
            'active' => Metrics::customersActive(),
            'online' => Metrics::customersOnline(),
            'recent' => $recent,
        ];
    }
}
