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
}
