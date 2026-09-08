<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Gateway;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Models\Meta;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\GatewayRules\Models\GatewayRule;

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
 * The reference's last two fields are here too, and both act rather than decorate:
 *
 * - **Order Form Template** — WHMCS offers eight cart layouts. Eight names against one
 *   layout would be a menu that changes nothing, so this offers the two the storefront
 *   genuinely draws: standard cards, and a compact one-row-per-product list.
 *   `themes/proxy/views/products/index.blade.php` branches on it.
 * - **Available Payment Gateways** — written as `GatewayRule` rows scoped to this category,
 *   so the choice is enforced by the same engine that already answers `canUseGateway()` at
 *   checkout. It is a real restriction, not a preference nothing consults.
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

    /** The reference's Order Form Template — the two layouts the storefront draws. */
    public string $orderForm = 'cards';

    /**
     * The reference's Available Payment Gateways.
     *
     * Empty means every gateway, which is what a group with no restriction should mean.
     * Saved as GatewayRule rows scoped to this category, so the choice is enforced by the
     * same engine that already answers `canUseGateway()` at checkout rather than being a
     * preference nothing consults.
     *
     * @var array<int, string>
     */
    public array $gateways = [];

    public static function canAccess(): bool
    {
        return CategoryResource::canCreate() || CategoryResource::canViewAny();
    }

    public function mount(): void
    {
        if ($this->groupId === null) {
            abort_unless(CategoryResource::canCreate(), 403);

            // A new group offers every gateway until someone says otherwise.
            $this->gateways = Gateway::orderBy('name')->pluck('extension')->all();

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
        $this->orderForm = array_key_exists((string) ($meta['order_form'] ?? ''), Meta::ORDER_FORMS)
            ? (string) $meta['order_form']
            : 'cards';

        // The stored restriction is a set of denies, so what is *ticked* is everything not
        // denied. Reading it back this way keeps the form showing what the customer sees.
        $all = Gateway::orderBy('name')->pluck('extension')->all();

        $denied = class_exists(GatewayRule::class)
            ? GatewayRule::where('category_id', $this->record->id)
                ->where('name', self::RULE_NAME_PREFIX . $this->record->id)
                ->pluck('gateway')->all()
            : [];

        $this->gateways = $denied === [] ? $all : array_values(array_diff($all, $denied));
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
        Meta::put($category, 'order_form', $this->orderForm);

        $this->saveGatewayRules($category);

        Notification::make()
            ->title('Group "' . $category->name . '" ' . ($this->record ? 'saved' : 'created'))
            ->success()->send();

        $this->redirect(Catalogue::getUrl());
    }

    /**
     * Write the group's payment-gateway restriction as GatewayRule rows.
     *
     * Deny rules rather than allow: the engine takes the first matching rule, so a set of
     * allows would also have to say what happens to everything unlisted. One deny per
     * gateway that was *not* chosen says exactly what is meant — "not this one, for this
     * group" — and leaves every other rule in the store untouched.
     *
     * Choosing none means no restriction, which is what an empty box should mean; the rows
     * are cleared and the group falls back to whatever the store allows generally.
     *
     * Rules this screen owns carry a generated `name` and are replaced only by matching
     * both that name and the category, so re-saving a group never disturbs a rule somebody
     * wrote by hand on the Gateway Rules page. (`gateway_rules` has no `note` column —
     * `name` is the field it gives you, and it is required.)
     */
    private function saveGatewayRules(Category $category): void
    {
        if (!class_exists(GatewayRule::class)) {
            return;
        }

        $name = self::RULE_NAME_PREFIX . $category->id;

        GatewayRule::where('category_id', $category->id)->where('name', $name)->delete();

        // Every gateway chosen means "no restriction" just as much as none does.
        $all = Gateway::orderBy('name')->pluck('extension')->all();
        $chosen = array_values(array_intersect($all, $this->gateways));

        if ($chosen === [] || count($chosen) === count($all)) {
            return;
        }

        foreach (array_diff($all, $chosen) as $extension) {
            GatewayRule::create([
                'name' => $name,
                'gateway' => $extension,
                'category_id' => $category->id,
                'mode' => 'deny',
                'active' => true,
                'priority' => 10,
            ]);
        }
    }

    /** Marks the rules this screen writes, so it only ever replaces its own. */
    private const RULE_NAME_PREFIX = 'Payment methods for group #';

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
            'allGateways' => Gateway::orderBy('name')->get(['id', 'name', 'extension']),
            // The storefront prefix the slug is appended to, so the field reads as a URL
            // the way the reference's does.
            'urlPrefix' => rtrim((string) config('app.url'), '/') . '/store/',
            'cancelUrl' => Catalogue::getUrl(),
        ];
    }
}
