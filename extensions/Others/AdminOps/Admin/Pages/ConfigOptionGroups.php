<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ConfigOptionResource;
use App\Models\ConfigOption;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #42 — WHMCS's Configurable Option Groups as the navy list: the reference's intro,
 * its Create a New Group and Duplicate a Group buttons, and its Group Name / Description
 * grid. A Paymenter top-level ConfigOption is the reference's "group": it carries the
 * values (children) and is applied to products, which is exactly the sentence WHMCS's own
 * intro uses.
 */
class ConfigOptionGroups extends Page
{
    protected string $view = 'adminops::pages.config-option-groups';

    protected static ?string $slug = 'config-option-groups';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public bool $duplicating = false;

    public ?int $duplicateSource = null;

    public ?int $confirming = null;

    public static function canAccess(): bool
    {
        return ConfigOptionResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Configurable Option Groups';
    }

    /** The reference's own intro, verbatim. */
    public function getSubheading(): ?string
    {
        return 'Configurable options allow you to offer addons and customisation options with '
            . 'your products. Options are assigned to groups and groups can then be applied to products.';
    }

    public function toggleDuplicating(): void
    {
        $this->duplicating = !$this->duplicating;
    }

    /** WHMCS's Duplicate a Group: the group row, its values, and its product links. */
    public function duplicate(): void
    {
        if (!ConfigOptionResource::canCreate()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $this->validate(['duplicateSource' => 'required|exists:config_options,id'], attributes: ['duplicateSource' => 'group']);

        $source = ConfigOption::with(['children', 'products'])->findOrFail($this->duplicateSource);

        DB::transaction(function () use ($source): void {
            $copy = $source->replicate(['sort']);
            $copy->name = $source->name . ' (Copy)';
            $copy->save();

            foreach ($source->children as $child) {
                $childCopy = $child->replicate(['sort']);
                $childCopy->parent_id = $copy->id;
                $childCopy->save();
            }

            $copy->products()->sync($source->products->pluck('id'));
        });

        $this->reset(['duplicating', 'duplicateSource']);
        Notification::make()->title('Group duplicated')->body('The copy is named "(Copy)".')->success()->send();
    }

    public function runDelete(): void
    {
        $id = $this->confirming;
        $this->reset('confirming');

        $group = ConfigOption::withCount('products')->find($id);

        if (!$group || !ConfigOptionResource::canDelete($group)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        if ($group->products_count > 0) {
            Notification::make()->title('Group not deleted')
                ->body('This group is applied to products. Detach it from them first.')
                ->danger()->send();

            return;
        }

        DB::transaction(function () use ($group): void {
            $group->children()->delete();
            $group->delete();
        });

        Notification::make()->title('Group deleted')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'groups' => ConfigOption::whereNull('parent_id')
                ->withCount(['children', 'products'])
                ->orderBy('sort')->orderBy('id')->get()
                ->map(fn (ConfigOption $group) => [
                    'row' => $group,
                    'edit' => ConfigOptionResource::canEdit($group)
                        ? ConfigOptionResource::getUrl('edit', ['record' => $group])
                        : null,
                ]),
            'newUrl' => ConfigOptionResource::canCreate() ? ConfigOptionResource::getUrl('create') : null,
        ];
    }
}
