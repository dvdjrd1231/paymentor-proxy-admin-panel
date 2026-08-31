<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use App\Models\Gateway;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Issue #37 — WHMCS's Stripe Balance panel, top-left of its dashboard: Available and
 * Pending, straight from Stripe's balance API. Same five-minute cache key the
 * Transactions page uses, so the two screens can never show different money.
 */
class StripeBalance extends Widget
{
    protected string $view = 'adminops::widgets.stripe-balance';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = -4;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.widgets.overview');
    }

    protected function getViewData(): array
    {
        $balances = Cache::remember('invoiceops.gateway-balances', 300, function (): array {
            try {
                $secret = Gateway::where('extension', 'Stripe')->first()
                    ?->settings->firstWhere('key', 'stripe_secret_key')?->value;

                if (!$secret) {
                    return ['at' => now()->toIso8601String(), 'tiles' => []];
                }

                $response = Http::withToken($secret)->timeout(8)->get('https://api.stripe.com/v1/balance');

                if (!$response->ok()) {
                    return ['at' => now()->toIso8601String(), 'tiles' => []];
                }

                $tiles = [];
                foreach (['available' => 'Available', 'pending' => 'Pending'] as $key => $label) {
                    foreach ($response->json($key, []) as $bucket) {
                        $tiles[] = [
                            'gateway' => 'Stripe',
                            'label' => $label,
                            'amount' => '$' . number_format($bucket['amount'] / 100, 2) . ' ' . strtoupper($bucket['currency']),
                        ];
                    }
                }

                return ['at' => now()->toIso8601String(), 'tiles' => $tiles];
            } catch (\Throwable $e) {
                return ['at' => now()->toIso8601String(), 'tiles' => []];
            }
        });

        return ['tiles' => $balances['tiles']];
    }
}
