<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CategoryResource;
use App\Admin\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Models\Meta;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Products/Services page: the whole catalogue on one screen, ordered by dragging.
 *
 * @link docs/02b-admin-area.md
 */
class Catalogue extends Page
{
    protected string $view = 'adminops::pages.catalogue';

    protected static ?string $slug = 'catalogue';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /**
     * `sort` is an `unsignedTinyInteger`, so a list longer than this cannot be numbered.
     * Writing anyway would silently clamp the tail to 255 and scramble it.
     */
    private const MAX_POSITION = 255;

    public static function canAccess(): bool
    {
        return ProductResource::canViewAny() || CategoryResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Products/Services';
    }

    /** The reference's own intro paragraph, verbatim (issues #35 and #41). */
    public function getSubheading(): ?string
    {
        return 'This is where you configure all your products and services. Each product must be '
            . 'assigned to a group which can either be visible or hidden from the order page '
            . '(products may also be hidden individually). A product which is in a hidden group can '
            . 'still be ordered using the Direct Order Link shown when editing the package.';
    }

    /**
     * The extra attributes the reference's columns need, loaded once per render.
     *
     * @var array{product: array<int, array<string, string|null>>, category: array<int, array<string, string|null>>}
     */
    public array $meta = ['product' => [], 'category' => []];

    /** Configurable option names per product id, for the Features column. */
    public array $features = [];

    /** The product whose type is being changed, or null. */
    public ?int $typingId = null;

    /** ['product'|'category', id] awaiting the "Are you sure?" modal, or null. */
    public ?string $confirmKind = null;

    public ?int $confirmId = null;

    /** Store the product's type from the Type column's picker. */
    public function setType(int $productId, string $type): void
    {
        $this->typingId = null;

        if (!array_key_exists($type, Meta::PRODUCT_TYPES)) {
            return;
        }

        $product = Product::find($productId);

        if (!$product || !ProductResource::canEdit($product)) {
            $this->refuse('You do not have permission to edit this product.');

            return;
        }

        Meta::put($product, 'type', $type);

        Notification::make()->title($product->name . ' is now "' . Meta::PRODUCT_TYPES[$type] . '"')
            ->success()->send();
    }

    public function confirmDelete(string $kind, int $id): void
    {
        $this->confirmKind = in_array($kind, ['product', 'category'], true) ? $kind : null;
        $this->confirmId = $id;
    }

    /**
     * The reference's red delete dot, guarded: a product with services, or a group with
     * products or children, is refused — deleting those from a reorder screen would take
     * live billing history with it.
     */
    public function runDelete(): void
    {
        [$kind, $id] = [$this->confirmKind, $this->confirmId];
        $this->reset(['confirmKind', 'confirmId']);

        if ($kind === 'product' && $id) {
            $product = Product::withCount('services')->find($id);

            if (!$product || !ProductResource::canDelete($product)) {
                $this->refuse('You do not have permission to delete this product.');
            } elseif ($product->services_count > 0) {
                $this->refuse('This product has services attached. Cancel or move them first.');
            } else {
                $product->delete();
                Notification::make()->title('Product deleted')->success()->send();
            }

            return;
        }

        if ($kind === 'category' && $id) {
            $category = Category::withCount('products')->find($id);

            if (!$category || !CategoryResource::canDelete($category)) {
                $this->refuse('You do not have permission to delete this group.');
            } elseif ($category->products_count > 0 || Category::where('parent_id', $category->id)->exists()) {
                $this->refuse('This group still holds products or child groups. Empty it first.');
            } else {
                $category->delete();
                Notification::make()->title('Group deleted')->success()->send();
            }
        }
    }

    protected function getViewData(): array
    {
        // Ordered exactly as core orders them — `orderBy('sort')`, which in MySQL puts the
        // unplaced (NULL) rows first — so this page shows the order the storefront actually
        // renders rather than a tidier one of its own. `id` only breaks ties, which are all
        // the NULLs; the first drag on a list removes them.
        $categories = Category::query()
            ->with(['products' => fn ($query) => $query
                // configOptions eager-loaded for the Features column: without it this page
                // runs one query per product, which on a store this size is a hundred.
                ->with(['server', 'plans', 'configOptions'])
                ->orderBy('sort')
                ->orderBy('id'),
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $products = $categories->flatMap->products;

        // Both meta bags in two queries rather than one per row.
        $this->meta = [
            'product' => Meta::forMany(Product::class, $products),
            'category' => Meta::forMany(Category::class, $categories),
        ];

        $this->features = $products
            ->mapWithKeys(fn (Product $p): array => [$p->id => $p->configOptions->pluck('name')->all()])
            ->all();

        return [
            'tree' => $this->tree($categories),
            'canReorder' => $this->canReorder(),
            'canReorderCategories' => $this->canReorderCategories(),
            'canReorderProducts' => $this->canReorderProducts(),
            'productCount' => $categories->sum(fn (Category $category): int => $category->products->count()),
            'allProducts' => Product::orderBy('name')->get(['id', 'name']),
            // The reference's own three screens rather than core's resource forms, so the
            // wording and shape match the screenshots (Leandro, 2026-09-07).
            'urls' => [
                'newProduct' => ProductResource::canCreate() ? CreateProduct::getUrl() : null,
                'newCategory' => CategoryResource::canCreate() ? CreateProductGroup::getUrl() : null,
                'duplicate' => ProductResource::canCreate() ? DuplicateProduct::getUrl() : null,
            ],
        ];
    }

    /**
     * Categories nested under their parents.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<int, array{category: Category, children: array}>
     */
    private function tree(Collection $categories, ?int $parentId = null): array
    {
        return $categories
            ->where('parent_id', $parentId)
            ->map(fn (Category $category): array => [
                'category' => $category,
                'children' => $this->tree($categories, $category->id),
            ])
            ->values()
            ->all();
    }

    // ── What each row shows ──────────────────────────────────────────────────────────────
    // On the page rather than in the view, as ClientSummary does: a Blade file reaching into
    // `App\Admin\Resources` for a URL, or deciding what "one-time" is called, puts knowledge
    // in two places.

    /**
     * The reference's Type column: the product's type, then the module it provisions
     * through in brackets — "Other (ProxyPanel)", exactly as Leandro's own WHMCS prints
     * every row of this catalogue.
     */
    public function typeLabel(Product $product): string
    {
        $type = Meta::PRODUCT_TYPES[$this->meta['product'][$product->id]['type'] ?? 'other']
            ?? Meta::PRODUCT_TYPES['other'];

        $server = $product->server;

        if (!$server) {
            return $type;
        }

        // Case-insensitively: a server named "proxyPanel" running the "ProxyPanel"
        // extension is one thing, and printing it twice said nothing twice.
        $module = strcasecmp($server->name, $server->extension) === 0
            ? $server->name
            : $server->extension;

        return $type . ' (' . $module . ')';
    }

    /** The reference's Features column. */
    public function featuresLabel(Product $product): string
    {
        $names = $this->features[$product->id] ?? [];

        if ($names === []) {
            return '-';
        }

        return count($names) === 1 ? $names[0] : count($names) . ' options';
    }

    /** The reference's Refresh Feature Status button. */
    public function refreshFeatures(): void
    {
        $products = Product::with(['configOptions', 'server'])->get();

        $withFeatures = $products->filter(fn (Product $p): bool => $p->configOptions->isNotEmpty())->count();
        $withoutModule = $products->filter(fn (Product $p): bool => $p->server === null)->count();

        Notification::make()
            ->title('Feature status refreshed')
            ->body(sprintf(
                '%d of %d product(s) carry configurable options. %s',
                $withFeatures,
                $products->count(),
                $withoutModule === 0
                    ? 'Every product has a provisioning module.'
                    : $withoutModule . ' have no module and will not provision automatically.',
            ))
            ->success()
            ->send();
    }

    /**
     * The reference's Pay Type column. A Paymenter product can carry several plans — a
     * monthly and an annual — so this can legitimately be more than one word, where WHMCS
     * only ever has one.
     */
    public function payTypeLabel(Product $product): string
    {
        $labels = [
            'recurring' => 'Recurring',
            'one-time' => 'One Time',
            'free' => 'Free',
        ];

        $types = $product->plans
            ->pluck('type')
            ->unique()
            ->map(fn (string $type): string => $labels[$type] ?? ucfirst($type))
            ->values();

        return $types->isEmpty() ? 'Not priced' : $types->implode(' · ');
    }

    /**
     * The reference's Auto Setup column. Paymenter provisions a service the moment its
     * order is paid — the reference's "After First Payment" — and a product with no
     * server module is never set up automatically.
     */
    public function autoSetupLabel(Product $product): string
    {
        return $product->server ? 'After First Payment' : '—';
    }

    public function productUrl(Product $product): ?string
    {
        return ProductResource::canEdit($product)
            ? ProductResource::getUrl('edit', ['record' => $product])
            : null;
    }

    /** The group's own editor — ours, not core's. */
    public function categoryUrl(Category $category): ?string
    {
        return CategoryResource::canEdit($category)
            ? CreateProductGroup::getUrl(['group' => $category->id])
            : null;
    }

    /**
     * Reorder the products of one category.
     *
     * @param  array<int, int|string>  $ids  every product in that category, in the new order
     */
    public function reorderProducts(int $categoryId, array $ids): void
    {
        if (!$this->canReorderProducts()) {
            $this->refuse('You do not have permission to reorder products.');

            return;
        }

        $ids = array_map('intval', $ids);

        // The browser sends the list, so the list is not to be trusted with *which* rows it
        // is allowed to renumber. Anything not already in this category is refused outright
        // rather than skipped: a mismatch means the page is stale or the request was forged,
        // and renumbering the remainder of a list you cannot see is worse than doing nothing.
        $actual = Product::query()
            ->where('category_id', $categoryId)
            ->pluck('id')
            ->all();

        if (!$this->sameSet($ids, $actual)) {
            $this->refuse('That group changed while you were dragging. Reload the page and try again.');

            return;
        }

        $this->renumber(Product::class, $ids);
    }

    /**
     * Reorder the categories under one parent — `null` for the top level.
     *
     * @param  array<int, int|string>  $ids  every category at that level, in the new order
     */
    public function reorderCategories(?int $parentId, array $ids): void
    {
        if (!$this->canReorderCategories()) {
            $this->refuse('You do not have permission to reorder categories.');

            return;
        }

        $ids = array_map('intval', $ids);

        $actual = Category::query()
            ->where('parent_id', $parentId)
            ->pluck('id')
            ->all();

        if (!$this->sameSet($ids, $actual)) {
            $this->refuse('The groups changed while you were dragging. Reload the page and try again.');

            return;
        }

        $this->renumber(Category::class, $ids);
    }

    /**
     * Write positions 1..n in one transaction.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, int>  $ids
     */
    private function renumber(string $model, array $ids): void
    {
        if (count($ids) > self::MAX_POSITION) {
            $this->refuse(
                'This list is longer than ' . self::MAX_POSITION . ' rows, which is as far as the '
                . '`sort` column counts. The order was not saved.'
            );

            return;
        }

        DB::transaction(function () use ($model, $ids): void {
            foreach ($ids as $position => $id) {
                $model::whereKey($id)->update(['sort' => $position + 1]);
            }
        });
    }

    /**
     * A silent success is the point — the row is already where it was dropped, and a toast
     * on every drag would be four toasts to dismiss after ordering four products. Only a
     * refusal has something to say.
     */
    private function refuse(string $message): void
    {
        Notification::make()
            ->title('Order not saved')
            ->body($message)
            ->danger()
            ->send();
    }

    /** @param  array<int, int>  $a  @param  array<int, int>  $b */
    private function sameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    private function canReorder(): bool
    {
        return $this->canReorderProducts() || $this->canReorderCategories();
    }

    private function canReorderProducts(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.products.update');
    }

    private function canReorderCategories(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.categories.update');
    }
}
