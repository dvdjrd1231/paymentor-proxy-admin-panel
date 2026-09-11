<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ConfigOptionResource;
use App\Models\Category;
use App\Models\ConfigOption;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's Create a New Group / Edit Group screen, to Leandro's screenshots of
 * `configproductoptions.php?action=managegroup` (2026-09-08).
 */
class EditConfigOptionGroup extends Page
{
    protected string $view = 'adminops::pages.edit-config-option-group';

    protected static ?string $slug = 'config-option-group';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Null while creating. */
    public ?ConfigOption $group = null;

    public array $form = ['name' => '', 'description' => '', 'type' => 'select', 'hidden' => false];

    /** Product ids this group is applied to. */
    public array $productIds = [];

    /** `/admin/config-option-group` creates; `/admin/config-option-group/7` edits. */
    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record?}';
    }

    public static function canAccess(): bool
    {
        return ConfigOptionResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Configurable Option Groups';
    }

    public function getHeading(): string
    {
        return 'Configurable Option Groups';
    }

    public function getSubheading(): ?string
    {
        return $this->group ? 'Edit Group' : 'Create a New Group';
    }

    public function mount(int|string|null $record = null): void
    {
        abort_unless(static::canAccess(), 403);

        if ($record !== null) {
            $this->group = ConfigOption::with('children')->whereNull('parent_id')->findOrFail($record);

            abort_unless(ConfigOptionResource::canEdit($this->group), 403);

            $this->form = [
                'name' => (string) $this->group->name,
                'description' => (string) ($this->group->description ?? ''),
                'type' => (string) ($this->group->type ?: 'select'),
                'hidden' => (bool) $this->group->hidden,
            ];

            $this->productIds = $this->group->products()->pluck('products.id')
                ->map(fn ($id) => (string) $id)->all();

            return;
        }

        abort_unless(ConfigOptionResource::canCreate(), 403);
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.description' => 'nullable|string|max:255',
            'form.type' => 'required|in:text,number,select,radio,checkbox,slider',
            'productIds.*' => 'integer|exists:products,id',
        ], attributes: ['form.name' => 'group name', 'form.description' => 'description']);

        DB::transaction(function (): void {
            if ($this->group) {
                $this->group->update([
                    'name' => $this->form['name'],
                    'description' => $this->form['description'] ?: null,
                    'type' => $this->form['type'],
                    'hidden' => (bool) $this->form['hidden'],
                ]);
            } else {
                $this->group = ConfigOption::create([
                    'name' => $this->form['name'],
                    'description' => $this->form['description'] ?: null,
                    'type' => $this->form['type'],
                    'hidden' => (bool) $this->form['hidden'],
                ]);
            }

            $this->group->products()->sync(array_map('intval', $this->productIds));
        });

        Notification::make()->title('Group saved')->success()->send();

        // A group created here has an id now, so the URL should be the one that edits it —
        // otherwise pressing Save again would make a second group with the same name.
        $this->redirect(static::getUrl(['record' => $this->group->id]));
    }

    protected function getViewData(): array
    {
        // The reference names each product "Group - Product", so one list box can hold the
        // whole catalogue without two products of the same name being indistinguishable.
        $products = Category::with(['products' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')->get()
            ->flatMap(fn (Category $category) => $category->products->map(fn ($product) => [
                'id' => $product->id,
                'label' => $category->name . ' - ' . $product->name,
            ]));

        return [
            'products' => $products,
            'listUrl' => ConfigOptionGroups::getUrl(),
            // The values live on core's form, which already prices each one per currency.
            'valuesUrl' => $this->group && ConfigOptionResource::canEdit($this->group)
                ? ConfigOptionResource::getUrl('edit', ['record' => $this->group])
                : null,
        ];
    }
}
