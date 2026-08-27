<?php

/**
 * Products/Services drag ordering — the server-side half.
 *
 * The dragging is a browser thing and is checked in a browser. What this proves is what the
 * page does with the list the browser sends, which is the part that can go wrong quietly:
 *
 *   1. A reorder renumbers the whole list 1..n, so no `NULL` and no tie survives it.
 *   2. The order the page then shows is the order the storefront reads.
 *   3. A list that does not match the category exactly is refused, and nothing is written.
 *      This is the one that matters: the ids come from the client, so without it a crafted
 *      request could renumber rows in a group the sender cannot even see.
 *   4. Categories reorder the same way, scoped to one parent.
 *
 * Writes to `products.sort` and `categories.sort` and **puts every value back** — the
 * originals are captured first and restored in a `finally`, including on failure.
 *
 *   php scripts/test-catalogue-order.php
 */
// Imports sit above the bootstrap, not below it, and that is load-bearing: PHP resolves
// aliases in parse order, so a `use` declared after this block does not apply to it and
// `Kernel::class` there resolves to a non-existent global `Kernel`. Pint keeps the block
// wherever it finds it and shortens any fully-qualified name back to the alias, so above
// is the only arrangement that survives both. Found by running the script.
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\Catalogue;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
// Fully qualified, not the imported alias: Pint orders `use` statements after this
// bootstrap block, and PHP resolves aliases in parse order — an alias declared below
// this line does not apply to it, so `Kernel::class` would resolve to a global `Kernel`
// that does not exist. Found by running it.
$app->make(Kernel::class)->bootstrap();

$steps = [];

function step(string $label, bool $ok, string $detail = ''): bool
{
    global $steps;
    $steps[] = $ok;
    printf('[ %s ] %-50s %s%s', $ok ? 'PASS' : 'FAIL', $label, $detail, PHP_EOL);

    return $ok;
}

/** The page refuses through a Filament notification, which needs a request it does not have here. */
function attempt(callable $call): void
{
    try {
        $call();
    } catch (Throwable) {
        // The refusal path reached the notification. What matters is what it wrote — nothing.
    }
}

// Both reorder methods are permission-gated, and a console process is signed in as nobody —
// so without this every call would be refused and the run would prove only that.
$admin = User::whereNotNull('role_id')
    ->with('role')
    ->get()
    ->first(fn (User $user): bool => $user->hasPermission('admin.products.update')
        && $user->hasPermission('admin.categories.update'));

if (!$admin) {
    echo 'No administrator holds both update permissions — nothing to run as.', PHP_EOL;

    exit(1);
}

Auth::login($admin);

$category = Category::withCount('products')
    ->having('products_count', '>=', 2)
    ->orderByDesc('products_count')
    ->first();

if (!$category) {
    echo 'No category with two or more products — nothing to reorder.', PHP_EOL;

    exit(1);
}

$productSorts = Product::pluck('sort', 'id')->all();
$categorySorts = Category::pluck('sort', 'id')->all();

try {
    $page = new Catalogue;

    // ── Products ─────────────────────────────────────────────────────────────────────────
    $ids = $category->products()->orderBy('sort')->orderBy('id')->pluck('id')->all();
    $reversed = array_reverse($ids);

    $page->reorderProducts($category->id, $reversed);

    $written = Product::whereIn('id', $reversed)->pluck('sort', 'id');

    step('renumbered 1..n', $written->sort()->values()->all() === range(1, count($reversed)),
        count($reversed) . ' products');

    step('no NULL left behind', !$written->contains(null));

    // The order the storefront reads (App\Livewire\Products\Index) is the order the page
    // claims to have saved — checked by asking the database the same way core does.
    $stored = $category->products()->orderBy('sort')->orderBy('id')->pluck('id')->all();
    step('stored order is the dropped order', $stored === $reversed);

    // ── The tamper guard ─────────────────────────────────────────────────────────────────
    $foreign = Product::where('category_id', '!=', $category->id)->value('id');

    if ($foreign) {
        $before = Product::whereIn('id', $reversed)->pluck('sort', 'id')->all();

        // A real id, but from another group: the list no longer matches the category.
        attempt(fn () => $page->reorderProducts($category->id, [...array_slice($reversed, 1), $foreign]));

        step('list from another group refused',
            Product::whereIn('id', $reversed)->pluck('sort', 'id')->all() === $before);

        step('the foreign product was not touched',
            Product::whereKey($foreign)->value('sort') === ($productSorts[$foreign] ?? null));
    }

    $before = Product::whereIn('id', $reversed)->pluck('sort', 'id')->all();

    // A short list: renumbering it would leave the missing rows stranded at their old
    // positions, silently interleaved with the new ones.
    attempt(fn () => $page->reorderProducts($category->id, array_slice($reversed, 1)));

    step('partial list refused',
        Product::whereIn('id', $reversed)->pluck('sort', 'id')->all() === $before);

    // ── Categories ───────────────────────────────────────────────────────────────────────
    $topLevel = Category::whereNull('parent_id')->orderBy('sort')->orderBy('id')->pluck('id')->all();

    if (count($topLevel) >= 2) {
        $flipped = array_reverse($topLevel);
        $page->reorderCategories(null, $flipped);

        step('categories renumbered 1..n',
            Category::whereIn('id', $flipped)->pluck('sort')->sort()->values()->all() === range(1, count($flipped)),
            count($flipped) . ' groups');

        step('stored group order is the dropped order',
            Category::whereNull('parent_id')->orderBy('sort')->orderBy('id')->pluck('id')->all() === $flipped);

        $before = Category::whereIn('id', $flipped)->pluck('sort', 'id')->all();
        attempt(fn () => $page->reorderCategories(null, array_slice($flipped, 1)));

        step('partial group list refused',
            Category::whereIn('id', $flipped)->pluck('sort', 'id')->all() === $before);
    }
} finally {
    // Put the catalogue back exactly as it was found, whatever happened above.
    foreach ($productSorts as $id => $sort) {
        Product::whereKey($id)->update(['sort' => $sort]);
    }

    foreach ($categorySorts as $id => $sort) {
        Category::whereKey($id)->update(['sort' => $sort]);
    }

    echo PHP_EOL, 'restored ', count($productSorts), ' product and ', count($categorySorts),
    ' category sort values', PHP_EOL;
}

$passed = count(array_filter($steps));
printf('%d/%d passed%s', $passed, count($steps), PHP_EOL);

exit($passed === count($steps) ? 0 : 1);
