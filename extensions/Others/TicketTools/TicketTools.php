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
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Manage <b>Canned Responses</b> and <b>Ticket Notes</b> from the admin navigation once enabled.'),
            ],
            [
                'name' => 'department_routing',
                'label' => 'Department routing',
                'type' => 'textarea',
                'description' => 'Assign new tickets automatically, one rule per line as '
                    . '<code>Department = admin@example.com</code>. A ticket whose department matches is assigned to '
                    . 'that admin on creation. Leave empty to assign manually.',
                'required' => false,
            ],
        ];
    }

    /**
     * Auto-assign a new ticket from the department routing table.
     *
     * WHMCS routes by department; Paymenter stores the department but leaves assignment
     * manual. Rules are plain text so support staff can edit them without a deploy, and an
     * unmatched department simply stays unassigned rather than guessing.
     */
    private function routeTicket(\App\Models\Ticket $ticket): void
    {
        $rules = trim((string) $this->config('department_routing'));

        if ($rules === '' || $ticket->assigned_to || !$ticket->department) {
            return;
        }

        foreach (preg_split('/\r?\n/', $rules) as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }

            [$department, $email] = array_map('trim', explode('=', $line, 2));

            if (strcasecmp($department, (string) $ticket->department) !== 0) {
                continue;
            }

            $admin = \App\Models\User::where('email', $email)->whereNotNull('role_id')->first();

            if ($admin) {
                $ticket->forceFill(['assigned_to' => $admin->id])->saveQuietly();
                \Illuminate\Support\Facades\Log::channel('stack')->info('[TicketTools] ticket routed', [
                    'ticket' => $ticket->id,
                    'department' => $ticket->department,
                    'assigned_to' => $admin->email,
                ]);
            }

            return;
        }
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

        Event::listen(\App\Events\Ticket\Created::class, function ($event) {
            try {
                $this->routeTicket($event->ticket);
            } catch (\Throwable $e) {
                // Routing is a convenience; it must never stop a ticket being raised.
                \Illuminate\Support\Facades\Log::channel('stack')->warning('[TicketTools] routing failed', [
                    'ticket' => $event->ticket->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
