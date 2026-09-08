<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CategoryResource;
use App\Models\Category;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Models\Meta;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's Create Group screen (Leandro, 2026-09-07, screenshot of
 * `configproducts.php?action=creategroup`): the group's name, its storefront URL, and the
 * rest of what a group is, saved with Save Changes / Cancel Changes.
 *
 * It edits as well as creates — `/{record}` — and the catalogue's group edit icon comes
 * here rather than to core's category form. That is not tidiness: three of the fields below
 * live in this extension's table and core's form cannot show them, so without an edit route
 * they would be write-once.
 *
 * ## Where the reference's fields are stored
 *
 * `categories` carries only id, slug, name, description, image, parent_id, full_slug and
 * sort. Headline, Tagline and Hidden have no column there, and `Category` does not use core's
 * `HasProperties` trait, so they are stored in this extension's own `ext_ao_meta` table —
 * real storage, read back on edit, rather than controls that forget what you typed.
 *
 * Two of the reference's fields are still absent, because storing them would not make them
 * do anything:
 *
 * - **Order Form Template** — WHMCS ships eight cart layouts to choose between. This
 *   storefront renders one, from the active theme. A stored choice would change nothing.
 * - **Available Payment Gateways** — gateway availability here is decided per gateway by
 *   `canUseGateway()` and by the GatewayRules extension, which is where a restriction has
 *   to live to be enforced at checkout. Setting it per group would be ignored.
 *
 * **Group Features** is absent for the same reason it is greyed out on the reference until
 * you save: it belongs to a group that already exists.
 *
 * Parent Group is ours rather than the reference's — `parent_id` is core's and the
 * storefront renders nested groups, so a create form that could not set it would make
 * child groups unreachable from this page.
 */
class CreateProductGroup extends Page
{
    protected string $view = 'adminops::pages.create-product-group';

    /**
     * `/admin/create-product-group` creates; `?group=5` edits the same screen.
     *
     * The group is a query parameter rather than a path segment because Filament matches a
     * *required* page parameter (as EditInvoice's `/{record}` shows) but not an optional
     * one — `/{record?}` registered fine and then 404'd on every id. `#[Url]` is the
     * pattern already used here for exactly this, on Open New Ticket's `client`.
     */
    protected static ?string $slug = 'create-product-group';

    #[Url(as: 'group')]
    public ?int $groupId = null;

    /** Resolved from {@see $groupId} in mount(); null while creating. */
    public ?Category $record = null;

    /** Navigation is built by {@see WhmcsNavigation}; this is reached from Products/Services. */
    protected static bool $shouldRegisterNavigation = false;

    public string $name = '';

    public string $slugValue = '';

    public ?int $parentId = null;

    public string $description = '';

    /** The reference's order-form copy, stored against the group — see {@see Meta}. */
    public string $headline = '';

    public string $tagline = '';

    /** The reference's "Check if this is a hidden group". */
    public bool $hidden = false;

    public static function canAccess(): bool
    {
        return CategoryResource::canCreate() || CategoryResource::canViewAny();
    }

    public function mount(): void
    {
        if ($this->groupId === null) {
            abort_unless(CategoryResource::canCreate(), 403);

            return;
        }

        $this->record = Category::findOrFail($this->groupId);
        abort_unless(CategoryResource::canEdit($this->record), 403);

        $this->name = (string) $this->record->name;
        $this->slugValue = (string) $this->record->slug;
        $this->parentId = $this->record->parent_id;
        $this->description = (string) ($this->record->description ?? '');

        $meta = Meta::for($this->record);
        $this->headline = (string) ($meta['headline'] ?? '');
        $this->tagline = (string) ($meta['tagline'] ?? '');
        $this->hidden = (bool) ($meta['hidden'] ?? false);
    }

    public function getTitle(): string
    {
        return 'Products/Services';
    }

    public function getHeading(): string
    {
        return 'Products/Services';
    }

    /** The reference prints the action as a second line under the page title. */
    public function getSubheading(): ?string
    {
        return $this->record ? 'Edit Group' : 'Create Group';
    }

    /**
     * The URL follows the name until someone types their own, as the reference's does.
     * Only while creating: rewriting an existing group's slug because someone corrected a
     * typo in its name would silently break every saved link to it.
     */
    public function updatedName(): void
    {
        if ($this->record === null) {
            $this->slugValue = (string) Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $unique = 'unique:categories,slug' . ($this->record ? ',' . $this->record->id : '');

        $this->validate([
            'name' => 'required|string|max:255',
            'slugValue' => 'nullable|string|max:255|' . $unique,
            'parentId' => 'nullable|exists:categories,id',
            'description' => 'nullable|string|max:65535',
            'headline' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
        ], attributes: [
            'name' => 'product group name',
            'slugValue' => 'URL',
            'parentId' => 'parent group',
        ]);

        // A group cannot be its own parent, nor a child of its own descendant — either
        // makes the tree a loop, and the catalogue would recurse until it ran out of stack.
        if ($this->record && $this->parentId !== null && $this->wouldLoop($this->record, $this->parentId)) {
            $this->addError('parentId', 'A group cannot sit inside itself or one of its own child groups.');

            return;
        }

        $attributes = [
            'name' => $this->name,
            'slug' => $this->slugValue ?: Str::slug($this->name),
            'parent_id' => $this->parentId,
            'description' => $this->description ?: null,
        ];

        if ($this->record) {
            $this->record->update($attributes);
            $category = $this->record;
        } else {
            $category = Category::create($attributes);
        }

        Meta::put($category, 'headline', $this->headline);
        Meta::put($category, 'tagline', $this->tagline);
        Meta::put($category, 'hidden', $this->hidden);

        Notification::make()
            ->title('Group "' . $category->name . '" ' . ($this->record ? 'saved' : 'created'))
            ->success()->send();

        $this->redirect(Catalogue::getUrl());
    }

    /** Would making $parentId the parent of $category put the tree in a loop? */
    private function wouldLoop(Category $category, int $parentId): bool
    {
        $seen = [];

        for ($id = $parentId; $id !== null; ) {
            if ($id === $category->id || isset($seen[$id])) {
                return true;
            }

            $seen[$id] = true;
            $id = Category::whereKey($id)->value('parent_id');
        }

        return false;
    }

    protected function getViewData(): array
    {
        return [
            // A group is never offered itself as its own parent.
            'parents' => Category::when($this->record, fn ($q) => $q->whereKeyNot($this->record->id))
                ->orderBy('name')->get(['id', 'name']),
            // The storefront prefix the slug is appended to, so the field reads as a URL
            // the way the reference's does.
            'urlPrefix' => rtrim((string) config('app.url'), '/') . '/store/',
            'cancelUrl' => Catalogue::getUrl(),
        ];
    }
}
