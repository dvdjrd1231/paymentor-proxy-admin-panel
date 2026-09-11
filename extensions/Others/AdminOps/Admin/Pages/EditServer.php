<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ServerResource;
use App\Helpers\ExtensionHelper;
use App\Models\Server;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;

/** WHMCS's Add/Edit Server, replacing core's raw Filament form. */
class EditServer extends Page
{
    protected string $view = 'adminops::pages.edit-server';

    protected static ?string $slug = 'server';

    /** Reached from the Servers list, not the sidebar. */
    protected static bool $shouldRegisterNavigation = false;

    /** The server being edited, or null while adding. */
    public ?Server $server = null;

    public string $name = '';

    public string $extension = '';

    public bool $enabled = true;

    /** @var array<string, mixed> The module's own settings, keyed as the module names them. */
    public array $settings = [];

    /** The reference's advanced view is a toggle, not a second page. */
    public bool $advanced = false;

    public static function canAccess(): bool
    {
        return ServerResource::canViewAny();
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record?}';
    }

    public function getTitle(): string
    {
        return 'Servers';
    }

    public function getSubheading(): ?string
    {
        return $this->server ? 'Edit Server' : 'Add Server';
    }

    public function mount(?string $record = null): void
    {
        if ($record !== null) {
            $this->server = Server::findOrFail((int) $record);

            abort_unless(ServerResource::canEdit($this->server), 403);

            $this->name = (string) $this->server->name;
            $this->extension = (string) $this->server->extension;
            $this->enabled = (bool) $this->server->enabled;
            $stored = $this->server->settings->keyBy('key');

            foreach ($this->fields() as $option) {
                $this->settings[$option['name']] = $stored[$option['name']]->value
                    ?? ($option['default'] ?? null);
            }

            return;
        }

        abort_unless(ServerResource::canCreate(), 403);

        // The reference opens Add Server on the first module in the list, already selected,
        // because a blank module select shows no settings and reads as an empty form.
        $this->extension = (string) (array_key_first($this->modules()) ?? '');
    }

    /** Changing the module changes which settings there are, so the old ones go. */
    public function updatedExtension(): void
    {
        $this->settings = [];
        $this->resetValidation();
    }

    /** The reference's Test Connection. */
    public function test(): void
    {
        if ($this->extension === '') {
            return;
        }

        // A brand-new server has no record to test against; ExtensionHelper needs one, so it
        // gets an unsaved instance carrying the typed settings.
        $record = $this->server ?: new Server(['name' => $this->name ?: 'untested', 'extension' => $this->extension, 'type' => 'server']);

        if (!ExtensionHelper::hasFunction($record, 'testConfig')) {
            Notification::make()->title('This module cannot be tested')
                ->body($this->extension . ' does not offer a connection test, so the only way to know is to save and provision something.')
                ->warning()->send();

            return;
        }

        try {
            $result = ExtensionHelper::testConfig($record, $this->settings);
        } catch (\Throwable $e) {
            $result = $e->getMessage();
        }

        if ($result === true) {
            Notification::make()->title('Connection is good')
                ->body($this->extension . ' answered.')->success()->send();

            return;
        }

        Notification::make()->title('Connection failed')
            ->body(is_string($result) ? $result : 'The module rejected these settings.')
            ->danger()->send();
    }

    public function save(): void
    {
        $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('extensions', 'name')
                    ->where(fn ($q) => $q->where('type', 'server')->whereNull('deleted_at'))
                    ->ignore($this->server?->id),
            ],
            'extension' => 'required|string|in:' . implode(',', array_keys($this->modules())),
        ], attributes: ['name' => 'name', 'extension' => 'module']);

        if ($this->server) {
            abort_unless(ServerResource::canEdit($this->server), 403);

            // The module is fixed after creation, as core has it: the saved settings belong
            // to that module's shape, and swapping it would leave a server configured with
            // another module's keys.
            $this->server->update(['name' => $this->name, 'enabled' => $this->enabled]);
        } else {
            abort_unless(ServerResource::canCreate(), 403);

            $this->server = Server::create([
                'name' => $this->name,
                'extension' => $this->extension,
                'type' => 'server',
                'enabled' => $this->enabled,
            ]);
        }

        // Core's own write, field for field — the same rows, types and encrypted flags
        // EditGateway performs, so a server saved here is indistinguishable from one saved
        // through core's form.
        foreach ($this->fields() as $option) {
            $value = $this->settings[$option['name']] ?? null;

            $this->server->settings()->updateOrCreate(
                [
                    'key' => $option['name'],
                    'settingable_id' => $this->server->id,
                    'settingable_type' => $this->server->getMorphClass(),
                ],
                [
                    'type' => $option['database_type'] ?? 'string',
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'encrypted' => $option['encrypted'] ?? false,
                ],
            );
        }

        $this->server = $this->server->refresh()->load('settings');

        Notification::make()->title('Server saved')->success()->send();

        $this->redirect(static::getUrl(['record' => $this->server->id]));
    }

    /**
     * The chosen module's configuration fields.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        if ($this->extension === '') {
            return [];
        }

        try {
            return ExtensionHelper::getConfig('server', $this->extension) ?: [];
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /** @return array<string, string> Module name => label, as ExtensionHelper knows them. */
    private function modules(): array
    {
        $servers = ExtensionHelper::getExtensions('server');

        return array_combine(
            array_column($servers, 'name'),
            array_column($servers, 'name'),
        );
    }

    protected function getViewData(): array
    {
        return [
            'modules' => $this->modules(),
            // The chosen module's own fields, so the form shows what it actually needs.
            'fields' => $this->fields(),
            'listUrl' => ServersList::getUrl(),
            'canTest' => $this->extension !== '',
        ];
    }
}
