<?php

namespace Paymenter\Extensions\Others\TicketTools;

use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;

/**
 * Ticket Tools — the two ticket features Paymenter core lacks (spec item 3):
 *
 *  - **Quick replies (canned responses)**: a staff-managed library of reusable
 *    answers (`CannedResponse`), optionally scoped by department.
 *  - **Internal notes**: staff-only notes attached to a ticket (`TicketNote`),
 *    kept in their own table that the client theme never renders — so they are
 *    never exposed to customers.
 *
 * Everything else the spec asks for (departments, priorities, attachments, service
 * association, permission-based access, notifications) is already provided by core;
 * this extension only fills the two real gaps. No core edits — additive tables +
 * auto-discovered Filament resources.
 *
 * @link docs/modules/ticket-tools.md
 */
class TicketTools extends Extension
{
    public function getConfig($values = [])
    {
        return [[
            'name' => 'Notice',
            'type' => 'placeholder',
            'label' => new HtmlString('Manage <b>Canned Responses</b> and <b>Ticket Notes</b> from the admin navigation once enabled.'),
        ]];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/TicketTools/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/TicketTools/database/migrations');
    }

    public function boot()
    {
        // Permission-based access (spec item 3) — surfaced in the roles UI.
        Event::listen('permissions', function () {
            return [
                'admin.canned_responses.view' => 'View Canned Responses',
                'admin.canned_responses.manage' => 'Manage Canned Responses',
                'admin.ticket_notes.view' => 'View Ticket Notes',
                'admin.ticket_notes.manage' => 'Manage Ticket Notes',
            ];
        });
    }
}
