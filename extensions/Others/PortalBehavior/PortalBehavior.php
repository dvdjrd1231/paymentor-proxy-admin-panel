<?php

namespace Paymenter\Extensions\Others\PortalBehavior;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\PortalBehavior\Middleware\RedirectPortalHome;

/**
 * Portal entry behaviour, matched to the client's current WHMCS portal.
 *
 * On the reference portal the home URL is never a page of its own: a visitor is sent to
 * the login screen, a signed-in customer to their dashboard. Paymenter instead renders a
 * public storefront on `/`, which the client flagged in review.
 *
 * Done as middleware from an extension rather than a route so no core file changes: the
 * core `/` route stays registered and untouched, this simply answers first. Disabling the
 * extension restores the storefront homepage instantly.
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

    public function boot()
    {
        // Appending to the `web` group (not replacing anything) keeps this reversible and
        // free of core edits; the middleware ignores every request except GET /.
        app('router')->pushMiddlewareToGroup('web', RedirectPortalHome::class);

        // Serves the theme's Open Sans webfont — see routes.php for why it lives here.
        require __DIR__ . '/routes.php';
    }
}
