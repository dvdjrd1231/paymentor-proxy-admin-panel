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
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Products/Services page: the whole catalogue on one screen, ordered by dragging.
 *
 * Paymenter can already order both — `categories.sort` and `products.sort` are real columns
 * the storefront reads — but the two halves live on different screens and one of them is
 * three clicks deep: categories reorder on their own list, products only inside the Products
 * tab of the category you happen to be editing. The product list itself, which is the screen
 * you would reach for, groups by category and cannot be dragged at all. So "put the Monthly
 * plans above the Daily ones" means editing each category in turn, and there is nowhere to
 * see the resulting shape.
 *
 * This is that shape: every group, its products underneath, a handle on both. It is the one
 * page in this panel whose *purpose* is the order of things, so nothing else here is
 * editable — every row links to the core screen that owns it, and this page writes exactly
 * two columns.
 *
 * ## Scope, and why dragging cannot move a product between groups
 *
 * A drag reorders within its own list: products stay in their category, categories under
 * their parent. Moving a product to another category is a different operation — it changes
 * `category_id`, and with it the storefront URL, the breadcrumb and any link anyone has
 * saved — so it stays on the product's own edit page where it is deliberate and audited.
 * The reference behaves the same way.
 *
 * ## What a save writes
 *
 * The whole list, 1..n, not just the row that moved. `sort` is nullable and every row in
 * this store still holds `NULL`, so a partial write would leave a mix of ordered and
 * unordered rows whose relative order MySQL decides. Renumbering the list ends that on the
 * first drag.
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

    public function getSubheading(): ?string
    {
        return $this->canReorder()
            ? 'Drag a group or a product by its handle to change the order customers see. Saved as you drop.'
            : 'The order customers see. Reordering needs the update permission.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newProduct')
                ->label('Create a New Product')
                ->icon(Heroicon::Plus)
                ->url(fn (): string => ProductResource::getUrl('create'))
                ->visible(fn (): bool => ProductResource::canCreate()),

            Action::make('newCategory')
                ->label('Create a New Group')
                ->icon(Heroicon::FolderPlus)
                ->color('gray')
                ->url(fn (): string => CategoryResource::getUrl('create'))
                ->visible(fn (): bool => CategoryResource::canCreate()),
        ];
    }

    protected function getViewData(): array
    {
        // Ordered exactly as core orders them — `orderBy('sort')`, which in MySQL puts the
        // unplaced (NULL) rows first — so this page shows the order the storefront actually
        // renders rather than a tidier one of its own. `id` only breaks ties, which are all
        // the NULLs; the first drag on a list removes them.
        $categories = Category::query()
            ->with(['products' => fn ($query) => $query
                ->with(['server', 'plans'])
                ->orderBy('sort')
                ->orderBy('id'),
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return [
            'tree' => $this->tree($categories),
            'canReorder' => $this->canReorder(),
            'canReorderCategories' => $this->canReorderCategories(),
            'canReorderProducts' => $this->canReorderProducts(),
            'productCount' => $categories->sum(fn (Category $category): int => $category->products->count()),
        ];
    }

    /**
     * Categories nested under their parents.
     *
     * Flat in this store today, but `categories.parent_id` is core's and the storefront
     * renders children, so a page claiming to show the catalogue has to show them — and a
     * child that silently vanished from here would look like a deleted category.
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
     * The reference's Type column. Paymenter's equivalent of a WHMCS module is the server
     * this product provisions through — the server's own name, with the extension behind it
     * where they differ, because "Main panel" alone does not say what it is.
     */
    public function typeLabel(Product $product): string
    {
        $server = $product->server;

        if (!$server) {
            return 'None';
        }

        return $server->name === $server->extension
            ? $server->name
            : $server->name . ' (' . $server->extension . ')';
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

    public function productUrl(Product $product): ?string
    {
        return ProductResource::canEdit($product)
            ? ProductResource::getUrl('edit', ['record' => $product])
            : null;
    }

    public function categoryUrl(Category $category): ?string
    {
        return CategoryResource::canEdit($category)
            ? CategoryResource::getUrl('edit', ['record' => $category])
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
     *
     * The refused drag itself needs no undoing: Livewire re-renders after every action, and
     * the rows are keyed, so the morph puts the list back the way the database has it. The
     * screen cannot be left showing an order that was not saved.
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
