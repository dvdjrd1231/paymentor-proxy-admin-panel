<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ExtensionResource;
use App\Helpers\ExtensionHelper;
use App\Models\Extension;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #52 ("adjust all Paymenter admin windows to the current standard") — the last
 * screen still on core's raw Filament resource table. This is the reference's Addon
 * Modules screen: the navy Name / Type / Enabled grid plus a quick Enable/Disable
 * toggle and Edit, matching every other Setup-menu page converted this way (#45 gateways,
 * #46 currencies, #49 roles, #50 API credentials, #51 OAuth). Gateway and Server type
 * extensions are excluded — they have their own dedicated screens, same as core's list.
 * Enable/disable calls the extension's own enabled()/disabled() hook first, exactly like
 * core's EditExtension::handleRecordUpdate, so nothing skips its own setup/teardown.
 * "Install Extension" and the marketplace browser are not reproduced here — they stay on
 * core's own "Available Extensions" page, deliberately left in the Addons menu (see
 * {@see WhmcsNavigation::markSystemSettingsPlaced()}).
 */
class ExtensionsList extends Page
{
    protected string $view = 'adminops::pages.extensions-list';

    protected static ?string $slug = 'extensions-list';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?int $confirming = null;

    public bool $confirmEnable = false;

    public static function canAccess(): bool
    {
        return ExtensionResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Extensions';
    }

    public function confirm(int $id, bool $enable): void
    {
        $this->confirming = $id;
        $this->confirmEnable = $enable;
    }

    /** Mirrors core's EditExtension::handleRecordUpdate for the enabled flag exactly. */
    public function run(): void
    {
        [$id, $enable] = [$this->confirming, $this->confirmEnable];
        $this->reset(['confirming', 'confirmEnable']);

        $extension = Extension::find($id);

        if (!$extension || !ExtensionResource::canEdit($extension)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        if ($extension->enabled != $enable) {
            ExtensionHelper::call($extension, $enable ? 'enabled' : 'disabled', [$extension], mayFail: true);
        }

        $extension->update(['enabled' => $enable]);

        Notification::make()
            ->title($enable ? 'Extension enabled' : 'Extension disabled')
            ->success()->send();
    }

    protected function getViewData(): array
    {
        $installUrl = null;
        try {
            $installUrl = \App\Admin\Pages\Extension::getUrl(['tab' => 'installable']);
        } catch (\Throwable $e) {
        }

        return [
            'extensions' => Extension::whereNotIn('type', ['gateway', 'server'])->orderBy('name')->get(),
            'canEdit' => fn (Extension $extension) => ExtensionResource::canEdit($extension)
                ? ExtensionResource::getUrl('edit', ['record' => $extension])
                : null,
            'installUrl' => $installUrl,
        ];
    }
}
