<?php

/**
 * Take Paymenter's demo catalogue off the storefront.
 *
 * A stock Paymenter install seeds VPS Hosting, Web Hosting, Dedicated Servers and Game
 * Servers. This business sells IPv6 proxies only, and the shop menu lists every category
 * that has a visible product — so a leftover demo product puts a category the client does
 * not sell straight into their navigation.
 *
 * Two rules, both conservative:
 *
 *  - **Products are hidden, never deleted.** A product with services attached cannot be
 *    removed without destroying the services that reference it. `hidden` takes it out of
 *    the storefront while leaving every record intact, and is one click to undo.
 *  - **Only empty demo categories are deleted.** A category holding any product is left
 *    alone, so this can never remove something being sold.
 *
 *   php scripts/tidy-catalogue.php            # show what it would do
 *   php scripts/tidy-catalogue.php --apply
 */
$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;

$apply = in_array('--apply', $argv, true);
echo $apply ? "Applying.\n\n" : "Dry run — nothing will be written. Re-run with --apply.\n\n";

/** The category this business actually sells from. Everything else came from the demo seed. */
const KEEP_CATEGORY = 'proxies';

// ── Products outside the real catalogue ──────────────────────────────────────────────────
$strays = Product::whereHas('category', fn ($q) => $q->where('slug', '!=', KEEP_CATEGORY))
    ->where('hidden', false)
    ->get();

foreach ($strays as $p) {
    printf("[ %s ] hide product #%d %-24s (category %s, %d service(s) keep their record)\n",
        $apply ? ' ok ' : 'todo', $p->id, $p->name, $p->category->slug ?? '?',
        Service::where('product_id', $p->id)->count());

    if ($apply) {
        $p->update(['hidden' => true]);
    }
}

if ($strays->isEmpty()) {
    echo "[ ok  ] no stray products on the storefront\n";
}

// ── Empty demo categories ────────────────────────────────────────────────────────────────
$empty = Category::where('slug', '!=', KEEP_CATEGORY)->get()
    ->filter(fn ($c) => Product::where('category_id', $c->id)->count() === 0);

foreach ($empty as $c) {
    printf("[ %s ] remove empty category #%d %s\n", $apply ? ' ok ' : 'todo', $c->id, trim($c->name));

    if ($apply) {
        $c->delete();
    }
}

if ($empty->isEmpty()) {
    echo "[ ok  ] no empty demo categories\n";
}

// ── What a visitor will see ──────────────────────────────────────────────────────────────
// On a dry run the changes have not happened yet, so project them rather than reporting the
// current state under an "after" heading.
echo "\nStorefront after this change:\n";

$wouldHide = $strays->pluck('id')->all();
$wouldDrop = $empty->pluck('id')->all();

foreach (Category::orderBy('id')->get() as $c) {
    if (!$apply && in_array($c->id, $wouldDrop, true)) {
        printf("  %-18s removed\n", trim($c->name));
        continue;
    }

    $visible = Product::where('category_id', $c->id)->where('hidden', false)
        ->when(!$apply, fn ($q) => $q->whereNotIn('id', $wouldHide))
        ->count();

    printf("  %-18s %d visible product(s)%s\n", trim($c->name), $visible,
        $visible === 0 ? '  — not shown in the shop menu' : '');
}

if (!$apply) {
    echo "\nNothing was written. Re-run with --apply.\n";
}
