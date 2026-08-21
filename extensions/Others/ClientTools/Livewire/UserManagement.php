<?php

namespace Paymenter\Extensions\Others\ClientTools\Livewire;

use App\Livewire\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\ClientTools\Models\Contact;

/**
 * User Management — who can reach this account.
 *
 * The reference portal lists the account owner plus anyone invited to sign in. Here that
 * second group is the contacts flagged as sub-accounts, so inviting someone and adding a
 * contact create one record rather than two that can drift apart: this page invites and
 * revokes, and the Contacts page edits the same row.
 */
class UserManagement extends Component
{
    public string $inviteEmail = '';

    /** 'all' grants every permission; 'choose' reveals the checkboxes. */
    public string $invitePermissionMode = 'all';

    public array $invitePermissions = [];

    protected function rules(): array
    {
        return [
            'inviteEmail' => 'required|email|max:255',
            'invitePermissionMode' => 'required|in:all,choose',
            'invitePermissions' => 'array',
            'invitePermissions.*' => 'in:' . implode(',', Contact::PERMISSIONS),
        ];
    }

    /**
     * Invite someone by creating the sub-account contact they will be matched to.
     *
     * No mail is sent: Paymenter has no invitation-token flow, and a message promising
     * access that nothing can redeem would be worse than none. The record is created and
     * the operator can share credentials — the page states this rather than implying an
     * email went out.
     */
    public function invite()
    {
        $this->validate();

        $email = strtolower(trim($this->inviteEmail));

        if ($email === strtolower($this->ownerEmail())) {
            return $this->notify(__('clienttools.invite_is_owner'), 'error');
        }

        if (Contact::where('user_id', Auth::id())->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return $this->notify(__('clienttools.invite_duplicate'), 'error');
        }

        // A local part is a poor name, but it is better than a blank one and the person can
        // correct it on the Contacts page.
        $local = explode('@', $email)[0];

        Contact::create([
            'user_id' => Auth::id(),
            'first_name' => ucfirst($local),
            'last_name' => '',
            'email' => $email,
            'is_sub_account' => true,
            'permissions' => $this->invitePermissionMode === 'all'
                ? Contact::PERMISSIONS
                : array_values($this->invitePermissions),
        ]);

        $this->reset('inviteEmail', 'invitePermissions');
        $this->invitePermissionMode = 'all';

        return $this->notify(__('clienttools.invite_sent'));
    }

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

    private function ownerEmail(): string
    {
        return (string) Auth::user()->email;
    }

    public function render()
    {
        $user = Auth::user();

        // `user_sessions` is a core table with no model, so it is read through the query
        // builder. The most recent activity is what the reference shows as "Last Login".
        $lastActivity = DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->max('last_activity');

        return view('clienttools::user-management', [
            'owner' => $user,
            'lastLogin' => $lastActivity ? Carbon::parse($lastActivity) : null,
            'subAccounts' => Contact::where('user_id', $user->id)->subAccounts()->orderBy('email')->get(),
            'permissionKeys' => Contact::PERMISSIONS,
        ]);
    }
}
