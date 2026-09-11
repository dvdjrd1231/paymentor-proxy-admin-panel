<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ApiResource;
use App\Models\ApiKey;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #50 — WHMCS's Manage API Credentials, both tabs (Leandro, 2026-09-07: "the Role
 * Management modal contain many options").
 */
class ApiCredentials extends Page
{
    protected string $view = 'adminops::pages.api-credentials';

    protected static ?string $slug = 'api-credentials';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The reference's two tabs. */
    #[Url]
    public string $tab = 'credentials';

    public ?int $confirming = null;

    public ?int $confirmingRole = null;

    /** The reference's Generate New API Credential modal. */
    public bool $generating = false;

    public string $newName = '';

    public ?int $newUser = null;

    /** @var array<int, int> Chosen role ids. */
    public array $newRoles = [];

    /** The reference's Role Management modal — null closed, 0 creating, id editing. */
    public ?int $roleModal = null;

    public string $roleName = '';

    public string $roleDescription = '';

    /** @var array<int, string> */
    public array $rolePermissions = [];

    /** Which category the modal's left-hand list has open. */
    public string $category = '';

    public static function canAccess(): bool
    {
        return ApiResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Manage API Credentials';
    }

    /** The reference's own intro, verbatim. */
    public function getSubheading(): ?string
    {
        return 'API Credentials enable more effective and secure management of administrative '
            . 'access provided to external applications and devices.';
    }

    /**
     * Every API ability the platform knows about, grouped the way the reference's
     * "Allowed API Actions" list is — one entry per subject, its actions inside.
     *
     * @return array<string, array<string, string>>
     */
    public static function catalogue(): array
    {
        $fromExtensions = Arr::dot(array_merge_recursive(...Event::dispatch('api.permissions', []) ?: [[]]));
        $groups = [];

        foreach (array_merge(Arr::dot(config('permissions.api')), $fromExtensions) as $key => $label) {
            // admin.users.viewAny → "users", the subject the reference groups by.
            $groups[explode('.', $key)[1] ?? 'other'][$key] = $label;
        }

        return $groups;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->category = array_key_first(static::catalogue()) ?? '';
        $this->newUser = Auth::id();
    }

    // ── Credentials ─────────────────────────────────────────────────────────────

    public function toggleGenerating(): void
    {
        $this->generating = !$this->generating;
    }

    /**
     * Core's own token pattern, verbatim: PAYM + 64 hex chars, only the SHA-256 hash
     * stored, the plaintext shown once.
     */
    public function generate(): void
    {
        if (!ApiResource::canCreate()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $this->validate([
            'newName' => 'required|string|max:255',
            'newUser' => 'required|exists:users,id',
            'newRoles' => 'array',
            'newRoles.*' => 'exists:ext_api_roles,id',
        ], attributes: ['newName' => 'description', 'newUser' => 'admin user']);

        $token = 'PAYM' . bin2hex(random_bytes(32));

        $key = ApiKey::create([
            'name' => $this->newName,
            'token' => hash('sha256', $token),
            'user_id' => $this->newUser,
            'enabled' => true,
            'permissions' => [],
        ]);

        $this->assignRoles($key, $this->newRoles);

        $this->reset(['generating', 'newName', 'newRoles']);
        $this->newUser = Auth::id();

        Notification::make()->title('API credential generated')
            ->body("Copy the token now — it is not shown again.\n\n" . $token)
            ->persistent()
            ->success()->send();
    }

    /**
     * Record the assignment and write the abilities it produces into the credential's own
     * permissions column, which is what core's API middleware actually checks.
     *
     * @param  array<int, int|string>  $roleIds
     */
    private function assignRoles(ApiKey $key, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        DB::transaction(function () use ($key, $roleIds): void {
            DB::table('ext_api_key_roles')->where('api_key_id', $key->id)->delete();

            foreach ($roleIds as $roleId) {
                DB::table('ext_api_key_roles')->insert(['api_key_id' => $key->id, 'api_role_id' => $roleId]);
            }

            $granted = DB::table('ext_api_roles')->whereIn('id', $roleIds)->pluck('permissions')
                ->flatMap(fn ($json) => (array) json_decode((string) $json, true))
                ->unique()->values()->all();

            $key->update(['permissions' => $granted]);
        });
    }

    /**
     * The reference's Credential Management modal (Leandro, 2026-09-07: "edit function
     * should be worked with the modal ... now the page is redirect to other page and
     * working as complex").
     */
    public ?int $editing = null;

    public string $editDescription = '';

    /** @var array<int, int> */
    public array $editRoles = [];

    public function openEdit(int $id): void
    {
        $key = ApiKey::find($id);

        if (!$key || !ApiResource::canEdit($key)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $this->editing = $id;
        $this->editDescription = (string) $key->name;
        $this->editRoles = DB::table('ext_api_key_roles')->where('api_key_id', $id)
            ->pluck('api_role_id')->map(fn ($v) => (int) $v)->all();
    }

    public function closeEdit(): void
    {
        $this->reset(['editing', 'editDescription', 'editRoles']);
    }

    public function saveEdit(): void
    {
        $key = ApiKey::find($this->editing);

        if (!$key || !ApiResource::canEdit($key)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $this->validate([
            'editDescription' => 'required|string|max:255',
            'editRoles' => 'array',
            'editRoles.*' => 'exists:ext_api_roles,id',
        ], attributes: ['editDescription' => 'description']);

        $key->update(['name' => $this->editDescription]);
        $this->assignRoles($key, $this->editRoles);

        $this->closeEdit();
        Notification::make()->title('Credential updated')->success()->send();
    }

    public function runDelete(): void
    {
        $id = $this->confirming;
        $this->reset('confirming');

        $key = ApiKey::find($id);

        if (!$key || !ApiResource::canDelete($key)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        DB::table('ext_api_key_roles')->where('api_key_id', $key->id)->delete();
        $key->delete();

        Notification::make()->title('API credential revoked')
            ->body('Anything still using it stops authenticating immediately.')->success()->send();
    }

    // ── Roles ───────────────────────────────────────────────────────────────────

    /** Open the Role Management modal — empty for a new role, filled for an existing one. */
    public function openRole(?int $id = null): void
    {
        $this->roleModal = $id ?? 0;
        $this->category = array_key_first(static::catalogue()) ?? '';

        if (!$id) {
            $this->reset(['roleName', 'roleDescription', 'rolePermissions']);

            return;
        }

        $role = DB::table('ext_api_roles')->find($id);
        abort_unless((bool) $role, 404);

        $this->roleName = (string) $role->name;
        $this->roleDescription = (string) $role->description;
        $this->rolePermissions = (array) json_decode((string) $role->permissions, true);
    }

    public function closeRole(): void
    {
        $this->roleModal = null;
    }

    /** The modal's Check All / Uncheck All — scoped to the open category, as in the reference. */
    public function checkCategory(bool $on): void
    {
        $keys = array_keys(static::catalogue()[$this->category] ?? []);

        $this->rolePermissions = $on
            ? array_values(array_unique(array_merge($this->rolePermissions, $keys)))
            : array_values(array_diff($this->rolePermissions, $keys));
    }

    public function saveRole(): void
    {
        abort_unless(ApiResource::canCreate(), 403);

        $id = $this->roleModal ?: null;

        $this->validate([
            'roleName' => 'required|string|max:255|unique:ext_api_roles,name' . ($id ? ',' . $id : ''),
            'roleDescription' => 'nullable|string|max:255',
        ], attributes: ['roleName' => 'role name']);

        $known = array_keys(array_merge(...array_values(static::catalogue())));
        $permissions = array_values(array_intersect($this->rolePermissions, $known));

        $row = [
            'name' => $this->roleName,
            'description' => $this->roleDescription ?: null,
            'permissions' => json_encode($permissions),
            'updated_at' => now(),
        ];

        if ($id) {
            DB::table('ext_api_roles')->where('id', $id)->update($row);
        } else {
            $id = DB::table('ext_api_roles')->insertGetId($row + ['created_at' => now()]);
        }

        // A role is only worth having if changing it changes the credentials holding it.
        foreach (DB::table('ext_api_key_roles')->where('api_role_id', $id)->pluck('api_key_id') as $keyId) {
            if ($key = ApiKey::find($keyId)) {
                $this->assignRoles($key, DB::table('ext_api_key_roles')
                    ->where('api_key_id', $keyId)->pluck('api_role_id')->all());
            }
        }

        $this->roleModal = null;
        Notification::make()->title('API role saved')->success()->send();
    }

    public function runDeleteRole(): void
    {
        $id = $this->confirmingRole;
        $this->reset('confirmingRole');

        abort_unless(ApiResource::canCreate(), 403);

        $holders = DB::table('ext_api_key_roles')->where('api_role_id', $id)->pluck('api_key_id');

        DB::table('ext_api_roles')->where('id', $id)->delete();
        DB::table('ext_api_key_roles')->where('api_role_id', $id)->delete();

        // Recompute every credential that held it, so its abilities shrink to what its
        // remaining roles grant rather than silently keeping the deleted role's.
        foreach ($holders as $keyId) {
            if ($key = ApiKey::find($keyId)) {
                $this->assignRoles($key, DB::table('ext_api_key_roles')
                    ->where('api_key_id', $keyId)->pluck('api_role_id')->all());
            }
        }

        Notification::make()->title('API role deleted')
            ->body('Credentials that held it lost the abilities it granted.')->success()->send();
    }

    protected function getViewData(): array
    {
        $keys = ApiKey::orderBy('id')->get();
        // Core's ApiKey model carries user_id but no relation; resolved in one query here.
        $users = User::whereIn('id', $keys->pluck('user_id')->filter())->get()->keyBy('id');
        $roles = DB::table('ext_api_roles')->orderBy('name')->get();
        $assigned = DB::table('ext_api_key_roles')->get()->groupBy('api_key_id');

        // Core's ApiResource is a single manage screen — no create/edit routes — so Edit
        // lands there, where the per-credential fields (IP allow-list, active) live.
        $manage = null;
        try {
            $manage = ApiResource::getUrl('index');
        } catch (\Throwable $e) {
        }

        return [
            'keys' => $keys->map(fn (ApiKey $key) => [
                'row' => $key,
                'user' => $users[$key->user_id] ?? null,
                'roles' => collect($assigned[$key->id] ?? [])
                    ->map(fn ($pivot) => $roles->firstWhere('id', $pivot->api_role_id)?->name)
                    ->filter()->values(),
                'edit' => ApiResource::canEdit($key),
            ]),
            'roles' => $roles,
            'holders' => DB::table('ext_api_key_roles')->get()->groupBy('api_role_id')
                ->map(fn ($rows) => count($rows)),
            'admins' => User::whereNotNull('role_id')->orderBy('first_name')->get(),
        ];
    }
}
