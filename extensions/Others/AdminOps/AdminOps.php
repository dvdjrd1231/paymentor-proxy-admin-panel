<?php

namespace Paymenter\Extensions\Others\AdminOps;

use App\Classes\Extension\Extension;
use Illuminate\Support\HtmlString;

/**
 * Admin-area operational tooling (spec item 2).
 *
 * Ships a proxy-business Operations metrics widget for the admin dashboard
 * (`Admin/Widgets/OperationsOverview`). Admin branding (logo, name, favicon, dark
 * logo) is already settings-driven in Paymenter — see docs/02b-admin-area.md — so
 * this extension focuses on the operational-metrics dashboard the brief asks for.
 *
 * @link docs/02b-admin-area.md
 */
class AdminOps extends Extension
{
    public function getConfig($values = [])
    {
        return [[
            'name' => 'Notice',
            'type' => 'placeholder',
            'label' => new HtmlString('Adds an <b>Operations</b> metrics widget to the admin dashboard. See docs/02b-admin-area.md to enable widget discovery.'),
        ]];
    }

    public function boot()
    {
        $this->keepTheDailyLogWritable();
    }

    /**
     * Force the day's log file to be writable by every process that logs.
     *
     * Whichever process writes the first line of the day creates that file and it keeps
     * that process's ownership. Here the scheduler and every `artisan` run are root while
     * web requests are nginx, so on any day root got there first the file landed 0644
     * root-owned and nginx could not append.
     *
     * That is not a quiet degradation. Writing the log is part of handling the request, so
     * the failed write threw and the response became a 500 — with nothing recorded about
     * it, because the logger was what had broken. It showed up as the order pages failing,
     * since those are the ones that log (the panel-unreachable warning).
     *
     * This belongs in config/logging.php as `'permission' => 0666` on the daily channel,
     * and it is set there too. But config/ is NOT bind-mounted into the container — only
     * app, extensions, themes, lang, database/migrations and scripts are — so that file is
     * the image's copy and the change never reaches the running site. Extensions are
     * mounted, so setting it here is what actually takes effect. Remove this once
     * docker-compose mounts ./config and the value in that file is live.
     *
     * Setting config rather than chmod-ing: the mode then applies to whichever process
     * creates the file, instead of repairing one file after the fact — a chmod holds only
     * until midnight.
     */
    private function keepTheDailyLogWritable(): void
    {
        if (config('logging.channels.daily.permission') === null) {
            config(['logging.channels.daily.permission' => 0666]);
        }
    }
}
