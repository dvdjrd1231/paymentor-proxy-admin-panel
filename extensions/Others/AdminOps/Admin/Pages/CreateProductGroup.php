<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CategoryResource;
use App\Models\Category;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's Create Group screen (Leandro, 2026-09-07, screenshot of
 * `configproducts.php?action=creategroup`): the group's name, its storefront URL, and the
 * rest of what a group is, saved with Save Changes / Cancel Changes.
 *
 * ## What the reference asks for that this platform has no column for
 *
 * `categories` carries id, slug, name, description, image, parent_id, full_slug and sort.
 * So of the reference's eight fields, four have nowhere to go and are left out rather than
 * drawn dead — the standing rule on this project after Leandro asked for the disabled
 * fields on General Settings to be sorted out:
 *
 * - **Product Group Headline / Tagline** — marketing copy for WHMCS's order form. The
 *   storefront here renders `description`, which is offered instead and does the same job.
 * - **Order Form Template** — WHMCS ships eight cart layouts to choose between. This
 *   storefront has one, from the active theme.
 * - **Available Payment Gateways** — gateway availability here is decided per gateway by
 *   `canUseGateway()` and by the GatewayRules extension, not per product group.
 * - **Hidden** — there is no `hidden` column on a category. Products have one, and the
 *   catalogue marks them; a group is hidden by not publishing its products.
 *
 * **Group Features** is absent for the same reason it is greyed out on the reference until
 * you save: it belongs to a group that exists. Here there is no feature list at all.
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
