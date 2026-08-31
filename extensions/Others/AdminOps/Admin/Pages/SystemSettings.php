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

        $sections = [
            'General' => [
                ['General Settings', $url(\App\Admin\Pages\Settings::class)],
                ['Administrators', $url(\App\Admin\Resources\UserResource::class)],
                ['API Credentials', $url(\App\Admin\Resources\ApiResource::class)],
                ['Audit Log', $url(\App\Admin\Resources\AuditResource::class)],
            ],
            'Billing' => [
                ['Payment Gateways', $url(\App\Admin\Resources\GatewayResource::class)],
                ['Currencies', $url(\App\Admin\Resources\CurrencyResource::class)],
                ['Tax Rates', $url(\App\Admin\Resources\TaxRateResource::class)],
                ['Coupons', $url(\App\Admin\Resources\CouponResource::class)],
                ['Payment Fee Rules', $url(\Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource::class)],
                ['Gateway Rules', $url(\Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource::class)],
            ],
            'Products' => [
                ['Products/Services', $url(Catalogue::class)],
                ['Configurable Options', $url(\App\Admin\Resources\ConfigOptionResource::class)],
                ['Servers', $url(\App\Admin\Resources\ServerResource::class)],
                ['Client Fields', $url(\App\Admin\Resources\CustomPropertyResource::class)],
            ],
            'Communications' => [
                ['Email Templates', $url(\App\Admin\Resources\NotificationTemplateResource::class)],
                ['Email Log', $url(\App\Admin\Resources\EmailLogResource::class)],
            ],
            'Extensions' => [
                ['Manage Extensions', $url(\App\Admin\Pages\Extension::class)],
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
