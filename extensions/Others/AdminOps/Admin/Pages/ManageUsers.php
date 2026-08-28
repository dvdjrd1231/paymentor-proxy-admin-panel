<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\UserResource;
use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Models\UserSession;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Manage Users, to its screenshot: one search field over ID, names, Two Factor and
 * Last Login Time — the login-account view of the same people the clients screen lists.
 *
 * The columns the reference shows are the columns Paymenter actually records: Two Factor is
 * whether `tfa_secret` is set, and Last Login Time is the newest `user_sessions.last_activity`,
 * the same stamp the Who's Around panel reads. Nothing is invented — an install that has
 * never seen a login for a user shows "Never", which is the truth.
 *
 * A page, not a themed UserResource — same documented reason as {@see ViewSearchClients}.
 */
class ManageUsers extends Page
{
    protected string $view = 'adminops::pages.manage-users';

    protected static ?string $slug = 'manage-users';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** 100, as the reference pages it — "Showing 1 to 100" is in the screenshot. */
    public const PER_PAGE = 100;

    #[Url]
    public string $q = '';

    #[Url]
    public int $page = 1;

    public static function canAccess(): bool
    {
        return UserResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Manage Users';
    }

    public function search(): void
    {
        $this->page = 1;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    /** The row being edited in the reference's Manage User modal, or null when closed. */
    public ?int $editing = null;

    public array $mu = [];

    public function openUser(int $id): void
    {
        $user = User::whereNull('role_id')->findOrFail($id);

        $this->mu = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'tfa' => (bool) $user->tfa_secret,
            'company' => $user->properties()->where('key', 'company_name')->first()?->value,
        ];
        $this->editing = $id;
    }

    public function closeUser(): void
    {
        $this->editing = null;
        $this->mu = [];
    }

    public function saveUser(): void
    {
        $user = User::whereNull('role_id')->findOrFail($this->editing);

        $this->validate([
            'mu.first_name' => 'required|string|max:255',
            'mu.last_name' => 'required|string|max:255',
            'mu.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ], attributes: ['mu.first_name' => 'first name', 'mu.last_name' => 'last name', 'mu.email' => 'email address']);

        $user->update([
            'first_name' => $this->mu['first_name'],
            'last_name' => $this->mu['last_name'],
            'email' => $this->mu['email'],
            // The switch only turns two-factor off: enabling needs the user's authenticator,
            // which only they have. Clearing the secret is what WHMCS's toggle does too.
            'tfa_secret' => $this->mu['tfa'] ? $user->tfa_secret : null,
        ]);

        $this->closeUser();
        Notification::make()->title('User saved')->success()->send();
    }

    /** The dropdown's Password Reset: the same email the login page's Forgot Password sends. */
    public function resetPassword(int $id): void
    {
        $user = User::whereNull('role_id')->findOrFail($id);

        try {
            NotificationHelper::passwordResetNotification($user, ['url' => url(route('password.reset', [
                'token' => Password::createToken($user),
                'email' => $user->email,
            ], false))]);
            Notification::make()->title('Password reset email sent')->body($user->email)->success()->send();
        } catch (\Throwable $e) {
            Log::warning('Password reset email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            Notification::make()->title('Password reset email failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function deleteUser(): void
    {
        $user = User::whereNull('role_id')->findOrFail($this->editing);

        if ($user->services()->exists() || $user->invoices()->exists()) {
            Notification::make()->title('Cannot delete')
                ->body('This user has services or invoices; cancel and remove those first.')
                ->danger()->send();

            return;
        }

        try {
            $user->delete();
            $this->closeUser();
            Notification::make()->title('User deleted')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Delete failed')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        $users = $this->query()->paginate(self::PER_PAGE, page: $this->page);

        if ($this->page > 1 && $users->isEmpty()) {
            $this->page = max(1, $users->lastPage());
            $users = $this->query()->paginate(self::PER_PAGE, page: $this->page);
        }

        return ['users' => $users];
    }

    private function query()
    {
        $query = User::query()
            // Client accounts only, as WHMCS's Manage Users is: its 808 records are client
            // logins, and administrators live on their own screen. Leandro's point exactly —
            // client user management and administrator management are separate concerns,
            // so an admin never appears in this list. Administrators are managed from
            // Utilities → Administrators (core's Users list, where roles are assigned).
            ->whereNull('role_id')
            // One extra column beats a query per row: the newest session stamp, or NULL for
            // a user who has never signed in.
            ->addSelect(['last_login_at' => UserSession::query()
                ->selectRaw('MAX(last_activity)')
                ->whereColumn('user_id', 'users.id'),
            ])
            ->orderByDesc('id');

        if ($this->q !== '') {
            $query->where(function ($q): void {
                $q->where('first_name', 'like', '%' . $this->q . '%')
                    ->orWhere('last_name', 'like', '%' . $this->q . '%')
                    ->orWhere('email', 'like', '%' . $this->q . '%');
            });
        }

        return $query;
    }
}
