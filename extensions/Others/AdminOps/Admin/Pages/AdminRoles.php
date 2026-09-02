<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #49 — WHMCS's Administrator Roles: the intro, the Add New Role Group and
 * Duplicate Role Group buttons, and the Group Name / Assigned Admin Users grid with
 * edit and guarded delete. Rows are Paymenter's real roles; editing permissions stays
 * on core's form, which owns the permission matrix.
 */
class AdminRoles extends Page
{
    protected string $view = 'adminops::pages.admin-roles';

    protected static ?string $slug = 'admin-roles';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public bool $duplicating = false;

    public ?int $duplicateSource = null;

    public ?int $confirming = null;

    public static function canAccess(): bool
    {
        return RoleResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Administrator Roles';
    }

    /** The reference's own intro, adapted only where it names the product. */
    public function getSubheading(): ?string
    {
        return 'The administrator roles allow you to fine tune exactly what each of your '
            . 'admin users can do within the admin area.';
    }

    public function toggleDuplicating(): void
    {
        $this->duplicating = !$this->duplicating;
    }

    /** WHMCS's Duplicate Role Group: the role and its whole permission set. */
    public function duplicate(): void
    {
        if (!RoleResource::canCreate()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $this->validate(['duplicateSource' => 'required|exists:roles,id'], attributes: ['duplicateSource' => 'role group']);

        $source = Role::findOrFail($this->duplicateSource);
        $copy = $source->replicate();
        $copy->name = $source->name . ' (Copy)';
        $copy->save();

        $this->reset(['duplicating', 'duplicateSource']);
        Notification::make()->title('Role group duplicated')->body('The copy is named "(Copy)".')->success()->send();
    }

    public function runDelete(): void
    {
        $id = $this->confirming;
        $this->reset('confirming');

        $role = Role::find($id);

        if (!$role || !RoleResource::canDelete($role)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        // Core's Role::users() declares a pivot that does not exist in the schema —
        // admins carry role_id directly — so the count is taken from users itself.
        if (User::where('role_id', $role->id)->exists()) {
            Notification::make()->title('Role group not deleted')
                ->body('Admin users are still assigned to it. Reassign them first.')
                ->danger()->send();

            return;
        }

        $role->delete();
        Notification::make()->title('Role group deleted')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'roles' => Role::orderBy('id')->get()->map(fn (Role $role) => [
                'row' => $role,
                'assigned' => User::where('role_id', $role->id)->orderBy('email')->pluck('email'),
                'edit' => RoleResource::canEdit($role) ? RoleResource::getUrl('edit', ['record' => $role]) : null,
            ]),
            'newUrl' => RoleResource::canCreate() ? RoleResource::getUrl('create') : null,
        ];
    }
}
