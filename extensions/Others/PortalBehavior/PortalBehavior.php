<?php

namespace Paymenter\Extensions\Others\PortalBehavior;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\PortalBehavior\Middleware\RedirectPortalHome;

/**
 * Portal entry behaviour: on the reference portal `/` is never a page of its own — guests
 * go to login, customers to their dashboard. Paymenter renders a storefront there instead.
 *
 * Middleware from an extension rather than a route, so the core `/` route stays untouched
 * and disabling the extension restores the storefront immediately.
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

    /**
     * Cache-busting token for the theme stylesheet.
     *
     * The file is served with a one-year immutable cache, so without this a CSS change
     * would not reach anyone who had already loaded the site. Derived from the file's
     * own contents rather than a hand-maintained version, so it cannot be forgotten.
     *
     * The hash is memoised for the request and falls back to a fixed string when the file
     * is missing — the stylesheet 404s in that case anyway, and throwing here would take
     * down every page instead of just the styling.
     */
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
        // Appending to the `web` group (not replacing anything) keeps this reversible and
        // free of core edits; the middleware ignores every request except GET /.
        app('router')->pushMiddlewareToGroup('web', RedirectPortalHome::class);

        // Serves the theme's Open Sans webfont — see routes.php for why it lives here.
        require __DIR__ . '/routes.php';
    }
}
