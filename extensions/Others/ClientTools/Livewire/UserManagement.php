<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\ClientTools\Models\Contact;

/**
 * User Management — who can reach this account.
 *
 * The reference portal lists the account owner plus anyone invited to sign in. Here that
 * second group is the contacts flagged as sub-accounts (see Contacts), and the page also
 * surfaces the owner's live sessions from `user_sessions` so "who has access right now"
 * is answerable, which is the question the page exists to answer.
 */
class UserManagement extends Component
{
    /**
     * Revoke a sub-account's access without deleting the person's details — the contact
     * stays on the account, it simply can no longer sign in.
     */
    public function revoke(int $contactId)
    {
        Contact::where('user_id', Auth::id())
            ->findOrFail($contactId)
            ->update(['is_sub_account' => false, 'permissions' => []]);

        return $this->notify(__('clienttools.access_revoked'));
    }

    public function render()
    {
        $user = Auth::user();

        // `user_sessions` is a core table with no model, so it is read through the query
        // builder. Expired rows are excluded: a session past its expiry is not access.
        $sessions = DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('last_activity')
            ->limit(10)
            ->get();

        return view('clienttools::user-management', [
            'owner' => $user,
            'subAccounts' => Contact::where('user_id', $user->id)->subAccounts()->orderBy('first_name')->get(),
            'sessions' => $sessions,
        ]);
    }
}
