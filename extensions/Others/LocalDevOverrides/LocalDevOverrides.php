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
 * Paymenter takes its canonical URL from the `app_url` **database setting**, which
 * SettingsProvider copies into `config('app.url')` and then pins with
 * `URL::forceRootUrl()`. Point a local install at the shared development database and that
 * value is `https://paymenter-dev.7hoop.net`, so every generated link, form action, asset
 * and redirect leaves the machine — the local site looks up but nothing works, and a login
 * posts to the server instead.
 *
 * Changing the setting is not an option: it is the same row the live site reads.
 *
 * This re-applies a local URL *after* settings have loaded. Extensions boot from
 * AppServiceProvider::boot(), which runs after SettingsProvider, so this is the last word
 * without touching core.
 *
 * Two independent guards, both required, so this can never affect the server:
 *
 *   1. `APP_ENV=local`     — the server runs `production`
 *   2. `LOCAL_APP_URL=…`   — must be set explicitly in the local .env
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
