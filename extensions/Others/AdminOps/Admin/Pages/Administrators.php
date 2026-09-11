<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Administrator Users on the window standard. Staff only — client logins are
 * {@see ManageUsers}, which is the same split the reference keeps.
 */
class Administrators extends Page
{
    protected string $view = 'adminops::pages.administrators';

    protected static ?string $slug = 'administrators';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 50;

    #[Url]
    public bool $filter = false;

    #[Url]
    public string $q = '';

    #[Url]
    public string $role = '';

    #[Url]
    public int $page = 1;

    public ?int $confirmingDelete = null;

    public static function canAccess(): bool
    {
        return UserResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Administrator Users';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDelete = $id;
    }

    public function deleteAdmin(): void
    {
        $user = User::whereNotNull('role_id')->findOrFail($this->confirmingDelete);

        abort_unless(UserResource::canDelete($user), 403);

        if ($user->id === auth()->id()) {
            Notification::make()->title('You cannot delete your own account')->danger()->send();
            $this->confirmingDelete = null;

            return;
        }

        // Demoted, not destroyed: a staff row owns tickets, audits and orders, and deleting
        // it takes that trail with it. Clearing the role removes the admin.
        $user->update(['role_id' => null]);
        $this->confirmingDelete = null;

        Notification::make()->title('Administrator access removed')
            ->body($user->email . ' is now an ordinary client login.')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'rows' => User::query()
                ->with('role')
                ->whereNotNull('role_id')
                ->when($this->q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->where('first_name', 'like', '%' . $this->q . '%')
                    ->orWhere('last_name', 'like', '%' . $this->q . '%')
                    ->orWhere('email', 'like', '%' . $this->q . '%')))
                ->when($this->role !== '', fn ($query) => $query->where('role_id', (int) $this->role))
                ->orderBy('email')
                ->paginate(self::PER_PAGE, page: $this->page),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'canEdit' => fn (User $user) => UserResource::canEdit($user),
            'canDelete' => fn (User $user) => UserResource::canDelete($user),
        ];
    }
}
