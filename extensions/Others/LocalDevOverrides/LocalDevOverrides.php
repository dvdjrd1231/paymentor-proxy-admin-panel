<?php

namespace Paymenter\Extensions\Others\LocalDevOverrides;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Lets a local checkout share the server's database without being redirected to the server.
 *
 * @link docs/04-shared-dev-database.md
 */
#[ExtensionMeta(
    name: 'Local Dev Overrides',
    description: 'Keeps URLs local while sharing the server database. Development only.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class LocalDevOverrides extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'Development only. Active only when <code>APP_ENV=local</code> <b>and</b> '
                    . '<code>LOCAL_APP_URL</code> is set in <code>.env</code>. On the server both '
                    . 'are false, so enabling it there changes nothing. Use it when a local '
                    . 'checkout points at the shared development database, so links stay local '
                    . 'instead of pointing at the live site.'
                ),
            ],
        ];
    }

    public function boot()
    {
        $url = rtrim((string) env('LOCAL_APP_URL', ''), '/');

        // Both guards, deliberately. Either one alone would be easy to trip by accident.
        if ($url === '' || !app()->environment('local')) {
            return;
        }

        config(['app.url' => $url]);
        config(['settings.app_url' => $url]);

        // forceRootUrl was already called with the database value; call it again to win.
        URL::forceRootUrl($url);
        URL::forceScheme(Str::startsWith($url, 'https://') ? 'https' : 'http');

        // Storage URLs are built from app.url too, so they would otherwise point at the
        // server and 404 for anything only present locally.
        config(['filesystems.disks.public.url' => $url . '/storage']);
    }
}
