<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Administrator Roles editor — the Name field over the permission matrix, three
 * columns, with Check All / Uncheck All and the Save Changes / Cancel Changes pair
 * (Leandro, 2026-09-07, screenshots).
 *
 * One page for both create and edit, because the reference uses one form for both. Core's
 * own screens were a Filament resource form with a searchable checkbox list, and its edit
 * page answered **403** for role 1 — that is the "error" on `/admin/roles/1/edit`. It is
 * core refusing on purpose: {@see RoleResource::canEdit} returns `$record->id !== 1`, so
 * the built-in full-administrator group cannot be touched at all.
 *
 * The reference does let you edit Full Administrator, so this page does too, with the
 * guard that actually matters in its place: you may not remove your **own** role's access
 * to role management, because that is the one edit that cannot be undone from inside the
 * admin area. Locking the whole group instead — core's answer — also blocks renaming it
 * and granting permissions, neither of which can lock anybody out.
 */
class RoleGroup extends Page
{
    protected string $view = 'adminops::pages.role-group';

    protected static ?string $slug = 'role-group';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Null while creating — the reference's New Role Group is this same form, empty. */
    public ?Role $role = null;

    public string $name = '';

    /** @var array<int, string> The ticked permission keys. */
    public array $permissions = [];

    /** The reference's "All Permissions" master switch, which core stores as `*`. */
    public bool $all = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record?}';
    }

    public static function canAccess(): bool
    {
        return RoleResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Administrator Roles';
    }

    public function mount(int|string|null $record = null): void
    {
        abort_unless(static::canAccess(), 403);

        if ($record === null) {
            abort_unless(RoleResource::canCreate(), 403);

            return;
        }

        $this->role = Role::findOrFail((int) $record);
        $this->name = (string) $this->role->name;

        $stored = (array) $this->role->permissions;
        $this->all = in_array('*', $stored, true);
        $this->permissions = array_values(array_diff($stored, ['*']));
    }

    /**
     * Every permission the platform knows about, label by key, in the order the config
     * declares them so related ones stay together down a column. Extensions contribute
     * through the same `permissions` event core's own form listens to, so an extension
     * enabled later appears here without this page knowing about it.
     *
     * @return array<string, string>
     */
    public static function catalogue(): array
    {
        $fromExtensions = Arr::dot(array_merge_recursive(...Event::dispatch('permissions', []) ?: [[]]));
        $core = Arr::dot(config('permissions.role'));

        // `*` is the master switch, shown as its own control rather than a checkbox in
        // the grid — it is not one permission among many, it is all of them.
        unset($core['*']);

        return array_merge($core, $fromExtensions);
    }

    /** The grid's three columns, split so a group is never cut across a column break. */
    public function columns(): array
    {
        $groups = [];

        foreach (static::catalogue() as $key => $label) {
            // admin.users.create → "users"; the reference groups by subject the same way.
            $groups[explode('.', $key)[1] ?? 'other'][$key] = $label;
        }

        $columns = [[], [], []];
        $sizes = [0, 0, 0];

        foreach ($groups as $group => $items) {
            $target = array_search(min($sizes), $sizes, true);
            $columns[$target][$group] = $items;
            $sizes[$target] += count($items) + 1;
        }

        return $columns;
    }

    public function checkAll(): void
    {
        $this->permissions = array_keys(static::catalogue());
    }

    public function uncheckAll(): void
    {
        $this->permissions = [];
    }

    public function save()
    {
        $isNew = $this->role === null;

        abort_unless($isNew ? RoleResource::canCreate() : static::canAccess(), 403);

        $this->validate([
            'name' => 'required|string|max:255|unique:roles,name' . ($isNew ? '' : ',' . $this->role->id),
        ], attributes: ['name' => 'role group name']);

        $stored = $this->all
            ? ['*']
            : array_values(array_intersect($this->permissions, array_keys(static::catalogue())));

        if ($guard = $this->wouldLockMeOut($stored)) {
            Notification::make()->title('Not saved')->body($guard)->danger()->send();

            return null;
        }

        if ($isNew) {
            $this->role = Role::create(['name' => $this->name, 'permissions' => $stored]);
            Notification::make()->title('Role group created')->success()->send();

            return redirect()->to(static::getUrl(['record' => $this->role->id]));
        }

        $this->role->update(['name' => $this->name, 'permissions' => $stored]);
        Notification::make()->title('Changes saved')->success()->send();

        return null;
    }

    /**
     * The one edit nobody can undo: taking role management away from your own group.
     * Everything else — including stripping every other permission from role 1 — is the
     * admin's call, exactly as it is in the reference.
     *
     * @param  array<int, string>  $stored
     */
    private function wouldLockMeOut(array $stored): ?string
    {
        $me = Auth::user();

        if (!$this->role || !$me || $me->role_id !== $this->role->id) {
            return null;
        }

        $keeps = fn (string $permission) => in_array('*', $stored, true) || in_array($permission, $stored, true);

        if (!$keeps('admin.roles.viewAny') || !$keeps('admin.roles.update')) {
            return 'This is your own role group. Removing its role-management permissions '
                . 'would lock you out of this screen with no way back, so it is refused. '
                . 'Assign yourself to another group first.';
        }

        return null;
    }

    public function delete()
    {
        abort_unless($this->role && RoleResource::canDelete($this->role), 403);

        if (User::where('role_id', $this->role->id)->exists()) {
            Notification::make()->title('Role group not deleted')
                ->body('Admin users are still assigned to it. Reassign them first.')->danger()->send();

            return null;
        }

        $this->role->delete();
        Notification::make()->title('Role group deleted')->success()->send();

        return redirect()->to(AdminRoles::getUrl());
    }
}
