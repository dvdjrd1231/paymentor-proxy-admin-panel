# Support Ticket System (spec item 3)

The brief asks for a WHMCS-like ticket experience with: departments, priorities, quick
replies, internal notes, attachments, service association, permission-based access, and
automatic notifications.

## What core already provides — and what this project adds

| Feature | Provided by |
|---|---|
| **Departments** | Core — configured in **Admin → Settings** (`ticket_departments`); shown on the create-ticket form |
| **Priorities** | Core — low / medium / high on every ticket |
| **Attachments** | Core — upload/drag-drop on tickets and replies |
| **Service association** | Core — a ticket can be linked to a service |
| **Staff assignment** | Core — "Assigned To" on the admin ticket |
| **Permission-based access** | Core — Filament roles/gates (`admin.tickets.*`), extended here with canned-response / ticket-note permissions |
| **Automatic notifications** | Core email/in-app (`new_ticket_message`) + this project's Telegram layer (`Others/Notifications`) |
| **Quick replies (canned responses)** | **`Others/TicketTools`** ← added |
| **Internal notes** | **`Others/TicketTools`** ← added |

So only **two real gaps** existed; `Others/TicketTools` fills them without editing core
or duplicating what already ships.

## Quick replies (canned responses)

A staff-managed library of reusable answers.

- **Manage:** Admin → **Support → Canned Responses** (create/edit, optional department
  scope, active toggle).
- **Use:** when replying to a ticket, open Canned Responses, copy the body you need into
  the reply. (Optional inline "Insert" button in the reply editor is available as a
  documented enhancement — see *Optional inline integration* below.)

## Internal notes

Staff-only notes attached to a ticket, **never visible to customers**.

- Stored in a separate `ticket_notes` table that the **client theme never renders**, so
  there is no code path that could leak a note to a customer.
- **Manage:** Admin → **Support → Ticket Notes (internal)** — pick the ticket, write the
  note; the authoring staff member is recorded automatically.

## Files

- `database/migrations/*_create_ticket_tools_tables.php` — `canned_responses`, `ticket_notes`
- `Models/CannedResponse.php`, `Models/TicketNote.php`
- `Admin/Resources/CannedResponseResource.php`, `Admin/Resources/TicketNoteResource.php` (+ Pages)
- `TicketTools.php` — extension (migrations + permission registration)

## Enable

```
Admin → Extensions → TicketTools   (runs the migration)
```
Then use **Support → Canned Responses** and **Support → Ticket Notes (internal)**.

## Permissions

Registered via the core `permissions` event, so they appear in the roles UI:
`admin.canned_responses.view/manage`, `admin.ticket_notes.view/manage`.

## Optional inline integration

For an inline "Insert canned response" button and an "Add internal note" box **inside the
admin ticket reply screen**, a small addition to the core ticket view is required (Paymenter
exposes no hook there). If desired, it is added as a documented entry in
`docs/CORE-TOUCHPOINTS.md`. The standalone pages above are fully functional without it.
