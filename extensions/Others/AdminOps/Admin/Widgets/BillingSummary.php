<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Models\InvoiceTransaction;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Issue #37 — WHMCS's Billing panel: what came in Today, This Month, This Year and All
 * Time, in the reference's four colours. Straight sums over invoice transactions.
 */
class BillingSummary extends Widget
{
    protected string $view = 'adminops::widgets.billing-summary';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.widgets.overview');
    }

    protected function getViewData(): array
    {
        $sum = fn ($from) => (float) InvoiceTransaction::when($from, fn ($q) => $q->where('created_at', '>=', $from))->sum('amount');
        $currency = config('settings.default_currency', 'USD');
        $money = fn (float $v) => '$' . number_format($v, 2);

        return [
            'currency' => $currency,
            'figures' => [
                ['Today', $money($sum(now()->startOfDay())), 'ao-wg-green'],
                ['This Month', $money($sum(now()->startOfMonth())), 'ao-wg-orange'],
                ['This Year', $money($sum(now()->startOfYear())), 'ao-wg-pink'],
                ['All Time', $money($sum(null)), 'ao-wg-ink'],
            ],
        ];
    }
}
