<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #34 — WHMCS organises its settings as a landing grid of named areas; core's
 * Settings page is one long form. This page is the reference's organisation over
 * Paymenter's real surfaces: every tile is a link to the screen that owns that area.
 */
class SystemSettings extends Page
{
    protected string $view = 'adminops::pages.system-settings';

    protected static ?string $slug = 'system-settings';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'System Settings';
    }

    protected function getViewData(): array
    {
        $url = function (string $class, string $page = 'index'): ?string {
            try {
                if (!class_exists($class)) {
                    return null;
                }

                return method_exists($class, 'getUrl')
                    ? (is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl($page) : $class::getUrl())
                    : null;
            } catch (\Throwable $e) {
                return null;
            }
        };

        // [label, url, icon, description] — the reference's own card shape: a glyph, a
        // title, one line saying what is behind it. The icon set is the same Remix Icon
        // family used everywhere else in this skin, not a new one for this page alone.
        $sections = [
            'General' => [
                ['General Settings', $url(\App\Admin\Pages\Settings::class), 'ri-settings-3-line', 'Company name, timezone, language and the store\'s core configuration'],
                ['Administrators', $url(\App\Admin\Resources\UserResource::class), 'ri-shield-user-line', 'Staff accounts and what each one can do'],
                ['API Credentials', $url(\App\Admin\Resources\ApiResource::class), 'ri-key-2-line', 'Keys for talking to this store from the outside'],
                ['Audit Log', $url(\App\Admin\Resources\AuditResource::class), 'ri-history-line', 'Every change made in the admin, who made it and when'],
            ],
            'Billing' => [
                ['Payment Gateways', $url(\App\Admin\Resources\GatewayResource::class), 'ri-bank-card-line', 'Which gateways can take a payment, and their credentials'],
                ['Currencies', $url(\App\Admin\Resources\CurrencyResource::class), 'ri-money-dollar-circle-line', 'What this store charges in, and the exchange rates behind it'],
                ['Tax Rates', $url(\App\Admin\Resources\TaxRateResource::class), 'ri-percent-line', 'Rates applied to invoices by country or region'],
                ['Coupons', $url(\App\Admin\Resources\CouponResource::class), 'ri-coupon-3-line', 'Discount codes clients can redeem at checkout'],
                ['Payment Fee Rules', $url(\Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource::class), 'ri-price-tag-3-line', 'Surcharges added for a given gateway or amount'],
                ['Gateway Rules', $url(\Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource::class), 'ri-git-branch-line', 'Which gateways are offered for a given country or currency'],
            ],
            'Products' => [
                ['Products/Services', $url(Catalogue::class), 'ri-shopping-bag-3-line', 'The catalogue itself — every product this store sells'],
                ['Configurable Options', $url(\App\Admin\Resources\ConfigOptionResource::class), 'ri-equalizer-line', 'Choices a client makes at checkout, like region or plan size'],
                ['Servers', $url(\App\Admin\Resources\ServerResource::class), 'ri-server-line', 'The provisioning modules a product can be delivered through'],
                ['Client Fields', $url(\App\Admin\Resources\CustomPropertyResource::class), 'ri-list-settings-line', 'Extra fields collected on a client\'s profile'],
            ],
            'Communications' => [
                ['Email Templates', $url(\App\Admin\Resources\NotificationTemplateResource::class), 'ri-mail-settings-line', 'What every automatic email says, per event'],
                ['Email Log', $url(\App\Admin\Resources\EmailLogResource::class), 'ri-mail-line', 'Every email this store has sent, and whether it went out'],
            ],
            'Extensions' => [
                ['Manage Extensions', $url(\App\Admin\Pages\Extension::class), 'ri-puzzle-line', 'Every extension this store has, enabled or not'],
            ],
        ];

        // A tile with nowhere to go helps nobody; the grid shows what this install has.
        $sections = collect($sections)
            ->map(fn (array $tiles) => array_values(array_filter($tiles, fn ($t) => $t[1] !== null)))
            ->filter(fn (array $tiles) => $tiles !== [])
            ->all();

        return ['sections' => $sections];
    }
}
