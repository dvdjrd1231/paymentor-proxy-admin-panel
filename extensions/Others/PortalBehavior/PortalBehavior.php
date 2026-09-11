<?php

namespace Paymenter\Extensions\Others\PortalBehavior;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\PortalBehavior\Middleware\KeepStaffOutOfClientArea;
use Paymenter\Extensions\Others\PortalBehavior\Middleware\RedirectPortalHome;

/**
 * Portal entry behaviour: on the reference portal `/` is never a page of its own — guests
 * go to login, customers to their dashboard. Paymenter renders a storefront there instead.
 *
 * @link docs/modules/portal-behavior.md
 */
#[ExtensionMeta(
    name: 'Portal Behavior',
    description: 'WHMCS-style portal entry: guests land on login, customers on their dashboard.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class PortalBehavior extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'While enabled, <code>/</code> redirects: guests to the login page, '
                    . 'signed-in customers to their dashboard — the behaviour of the reference '
                    . 'portal. Disable to restore the public storefront homepage.'
                ),
            ],
        ];
    }

    /** Cache-busting token for the theme stylesheet. */
    public static function styleVersion(): string
    {
        static $version = null;

        if ($version !== null) {
            return $version;
        }

        $path = base_path('themes/proxy/assets/whmcs.css');

        return $version = is_file($path) ? substr(md5_file($path), 0, 8) : 'missing';
    }

    public function boot()
    {
        // Appended to the `web` group (nothing is replaced), so both are reversible and
        // need no core edit. RedirectPortalHome ignores everything except GET /;
        // KeepStaffOutOfClientArea ignores everything except staff in the client area.
        app('router')->pushMiddlewareToGroup('web', RedirectPortalHome::class);
        app('router')->pushMiddlewareToGroup('web', KeepStaffOutOfClientArea::class);

        // Serves the theme's Open Sans webfont — see routes.php for why it lives here.
        require __DIR__ . '/routes.php';
    }
}
