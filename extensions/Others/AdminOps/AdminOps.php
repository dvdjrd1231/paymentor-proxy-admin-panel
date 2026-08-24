<?php

namespace Paymenter\Extensions\Others\AdminOps;

use App\Classes\Extension\Extension;
use Illuminate\Support\HtmlString;

/**
 * An Operations metrics widget for the admin dashboard. Admin branding is already
 * settings-driven in Paymenter, so this covers only the metrics side.
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
     * Force the day's log file to be group-writable.
     *
     * Whichever process writes the first line of the day owns the file. The scheduler and
     * artisan run as root, web requests as nginx, so on a day root got there first nginx
     * could not append — and since logging is part of handling the request, that surfaced
     * as 500s on the order pages with nothing recorded, the logger being what broke.
     *
     * Set here rather than in config/logging.php (where it also is) because config/ is not
     * bind-mounted into the container and extensions/ is. Remove once ./config is mounted.
     * Setting the mode beats chmod: it applies to whoever creates the file, and a chmod
     * only holds until midnight.
     */
    private function keepTheDailyLogWritable(): void
    {
        if (config('logging.channels.daily.permission') === null) {
            config(['logging.channels.daily.permission' => 0666]);
        }
    }
}
