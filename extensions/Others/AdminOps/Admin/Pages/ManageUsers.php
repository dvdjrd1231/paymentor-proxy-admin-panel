<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\UserResource;
use App\Models\User;
use App\Models\UserSession;
use Filament\Pages\Page;
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

    public const PER_PAGE = 25;

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
