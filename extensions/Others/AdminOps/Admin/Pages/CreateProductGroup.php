<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CategoryResource;
use App\Models\Category;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\AdminOps\Models\Meta;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's Create Group screen (Leandro, 2026-09-07, screenshot of
 * `configproducts.php?action=creategroup`): the group's name, its storefront URL, and the
 * rest of what a group is, saved with Save Changes / Cancel Changes.
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

    protected static ?string $slug = 'create-product-group';

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
        return CategoryResource::canCreate();
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
        return 'Create Group';
    }

    /** The URL follows the name until someone types their own, as the reference's does. */
    public function updatedName(): void
    {
        $this->slugValue = (string) Str::slug($this->name);
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slugValue' => 'nullable|string|max:255|unique:categories,slug',
            'parentId' => 'nullable|exists:categories,id',
            'description' => 'nullable|string|max:65535',
            'headline' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
        ], attributes: [
            'name' => 'product group name',
            'slugValue' => 'URL',
            'parentId' => 'parent group',
        ]);

        $category = Category::create([
            'name' => $this->name,
            'slug' => $this->slugValue ?: Str::slug($this->name),
            'parent_id' => $this->parentId,
            'description' => $this->description ?: null,
        ]);

        Meta::put($category, 'headline', $this->headline);
        Meta::put($category, 'tagline', $this->tagline);
        Meta::put($category, 'hidden', $this->hidden);

        Notification::make()->title('Group "' . $category->name . '" created')->success()->send();

        $this->redirect(Catalogue::getUrl());
    }

    protected function getViewData(): array
    {
        return [
            'parents' => Category::orderBy('name')->get(['id', 'name']),
            // The storefront prefix the slug is appended to, so the field reads as a URL
            // the way the reference's does.
            'urlPrefix' => rtrim((string) config('app.url'), '/') . '/store/',
            'cancelUrl' => Catalogue::getUrl(),
        ];
    }
}
