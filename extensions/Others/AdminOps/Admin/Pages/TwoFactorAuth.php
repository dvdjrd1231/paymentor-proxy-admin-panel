<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\UserResource;
use App\Models\User;
use App\Models\UserAuthenticationLog;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** WHMCS's Two-Factor Authentication, under Staff Management. */
class TwoFactorAuth extends Page
{
    protected string $view = 'adminops::pages.two-factor-auth';

    protected static ?string $slug = 'two-factor-auth';

    /** Navigation is built by {@see \Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Which staff member's 2FA is awaiting an "Are you sure?". */
    public ?int $confirming = null;

    public static function canAccess(): bool
    {
        // Resetting someone's second factor is an account-security action, so it is gated on
        // the same permission as editing the user, not on merely being able to see the panel.
        return UserResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Two-Factor Authentication';
    }

    public function getSubheading(): ?string
    {
        return 'Two-factor authentication adds a one-time code to sign-in. '
            . 'Each person turns it on for their own account; this is where you see who has, '
            . 'and reset it for someone who has lost their device.';
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel);
    }

    /** Turn off a staff member's 2FA so they can sign in and set it up again. */
    public function resetTfa(): void
    {
        $id = $this->confirming;
        $this->confirming = null;

        $user = User::find($id);

        if (!$user || !$user->role_id) {
            return;
        }

        abort_unless(UserResource::canEdit($user), 403);

        if (!$user->tfa_secret) {
            return;
        }

        // Their own account is the one case where this is a foot-gun rather than a rescue:
        // an admin who resets their own 2FA has not been locked out, they have just removed
        // it, and the Security page is where that decision belongs.
        if ((int) $user->id === (int) Auth::id()) {
            Notification::make()->title('Use your own Security page')
                ->body('Turning off your own second factor is a change to your account, not a rescue — do it from Security so you set it up again in the same visit.')
                ->warning()->send();

            return;
        }

        $user->update(['tfa_secret' => null]);

        Notification::make()->title('Two-factor authentication reset')
            ->body(trim($user->first_name . ' ' . $user->last_name) . ' can sign in with their password alone until they set it up again. Tell them to.')
            ->success()->send();
    }

    protected function getViewData(): array
    {
        $staff = User::whereNotNull('role_id')
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'tfa_secret', 'role_id']);

        // One query for everyone's last sign-in rather than one per row.
        $lastSeen = Schema::hasTable('user_authentication_logs')
            ? UserAuthenticationLog::whereIn('user_id', $staff->pluck('id'))
                ->select('user_id', DB::raw('MAX(last_used_at) as seen'), DB::raw('COUNT(*) as places'))
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id')
            : collect();

        return [
            'staff' => $staff,
            'lastSeen' => $lastSeen,
            'onCount' => $staff->whereNotNull('tfa_secret')->count(),
            'selfId' => (int) Auth::id(),
        ];
    }
}
