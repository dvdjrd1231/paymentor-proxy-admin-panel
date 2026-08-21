<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

/**
 * Email History — every message the system has sent this customer.
 *
 * Backed by the core `email_logs` table, which already records subject, recipient, body
 * and delivery status. Rows are filtered to the signed-in user, so the page cannot show
 * another account's mail.
 */
class EmailHistory extends Component
{
    use WithPagination;

    /** Id of the row whose body is expanded, if any. */
    public ?int $open = null;

    public function toggle(int $id): void
    {
        $this->open = $this->open === $id ? null : $id;
    }

    public function render()
    {
        $emails = DB::table('email_logs')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(15);

        // The expanded body is fetched separately and re-scoped to the user, so an id
        // pushed in from the client cannot open a message belonging to someone else.
        $openEmail = $this->open
            ? DB::table('email_logs')->where('user_id', Auth::id())->where('id', $this->open)->first()
            : null;

        return view('clienttools::email-history', [
            'emails' => $emails,
            'openEmail' => $openEmail,
        ]);
    }
}
