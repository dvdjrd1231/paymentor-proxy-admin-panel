<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ProductResource;
use App\Helpers\ExtensionHelper;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Price;
use App\Models\Product;
use App\Models\Server;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\AdminOps\Models\Meta;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's Edit Product screen, to Leandro's screenshots of
 * `configproducts.php?action=edit` (2026-09-08): the tab strip over one product.
 *
 * ## The tabs that are here
 *
 * Details, Pricing, Module Settings, Configurable Options, Upgrades and Links — every one
 * backed by a real column or relation:
 *
 * - **Details** — `category_id`, `name`, `slug`, `description`, `stock`, `allow_quantity`,
 *   `per_user_limit`, `email_template`, `hidden`. Tagline, short description, colour,
 *   featured and retired have no column, so they live in `ext_ao_meta` alongside the
 *   product type the catalogue already reads.
 * - **Pricing** — `plans` and their `prices`. A Paymenter plan *is* a billing cycle, so the
 *   reference's grid of cycles becomes one row per plan, priced per currency.
 * - **Module Settings** — `server_id`, plus the module's own fields from
 *   `getProductConfig()`, which is the same descriptor shape the gateway editor renders.
 * - **Configurable Options**, **Upgrades**, **Links** — `config_option_products`,
 *   `product_upgrades`, and the storefront URLs derived from the slug.
 *
 * ## The tabs that are not
 *
 * - **Free Domain** — this deployment sells proxies and domains are switched off; see
 *   `docs/10-disable-domains.md`. Every control on that tab would be inert.
 * - **Cross-sells** — nothing in Paymenter recommends one product while ordering another,
 *   so a saved list would be read by nobody.
 * - **Custom Fields** — the reference defines them per product. Paymenter's
 *   `custom_properties` are defined per *model* and are already managed on Custom Client
 *   Fields; per-product fields are a different feature, not a screen away.
 * - **Other** — its contents are affiliate payout overrides, subdomain options and overage
 *   billing, none of which exist here. The two parts that do — per-user limit and the
 *   product's sort position — are on Details, where they are easier to find.
 */
class EditProduct extends Page
{
    protected string $view = 'adminops::pages.edit-product';

    protected static ?string $slug = 'product';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public Product $product;

    /** details | pricing | module | options | upgrades | links */
    public string $tab = 'details';

    public array $form = [];

    /** Meta-backed fields the reference shows that core has no column for. */
    public array $extra = [
        'type' => 'other', 'tagline' => '', 'short_description' => '',
        'colour' => '', 'featured' => false, 'retired' => false,
    ];

    /** One row per plan: [id, name, type, billing_period, billing_unit, prices[currency => [price, setup_fee]]]. */
    public array $plans = [];

    /** Module fields declared by the server extension, as name => value. */
    public array $moduleSettings = [];

    /** Config option group ids ticked. */
    public array $optionIds = [];

    /** Product ids this one can be upgraded to. */
    public array $upgradeIds = [];

    /** Product ids recommended alongside this one — the reference's Cross-sells. */
    public array $crossSellIds = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public static function canAccess(): bool
    {
        return ProductResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Products/Services';
    }

    public function getHeading(): string
    {
        return 'Products/Services';
    }

    public function getSubheading(): ?string
    {
        return 'Edit Product — ' . $this->product->name;
    }

    public function mount(int|string $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->product = Product::with(['plans.prices', 'configOptions', 'settings', 'server'])
            ->findOrFail($record);

        $this->load();
    }

    private function load(): void
    {
        $p = $this->product;

        $this->form = [
            'category_id' => $p->category_id,
            'name' => (string) $p->name,
            'slug' => (string) $p->slug,
            'description' => (string) ($p->description ?? ''),
            'stock' => $p->stock,
            'stock_enabled' => $p->stock !== null,
            'per_user_limit' => $p->per_user_limit,
            'allow_quantity' => (string) ($p->allow_quantity ?: 'disabled'),
            'email_template' => (string) ($p->email_template ?? ''),
            'hidden' => (bool) $p->hidden,
            'server_id' => $p->server_id,
            'sort' => $p->sort,
        ];

        $meta = Meta::for($p);
        $this->extra = [
            'type' => array_key_exists((string) ($meta['type'] ?? ''), Meta::PRODUCT_TYPES) ? $meta['type'] : 'other',
            'tagline' => (string) ($meta['tagline'] ?? ''),
            'short_description' => (string) ($meta['short_description'] ?? ''),
            'colour' => (string) ($meta['colour'] ?? ''),
            'featured' => ($meta['featured'] ?? null) === '1',
            'retired' => ($meta['retired'] ?? null) === '1',
        ];

        $this->plans = $p->plans->map(fn (Plan $plan): array => [
            'id' => $plan->id,
            'name' => (string) $plan->name,
            'type' => (string) $plan->type,
            'billing_period' => (int) $plan->billing_period,
            'billing_unit' => (string) $plan->billing_unit,
            'prices' => $plan->prices->mapWithKeys(fn (Price $price): array => [
                $price->currency_code => [
                    'price' => number_format((float) $price->price, 2, '.', ''),
                    'setup_fee' => number_format((float) $price->setup_fee, 2, '.', ''),
                ],
            ])->all(),
        ])->values()->all();

        // `settings` is keyed by `key`, not `name`. Reading the wrong column returned all
        // nulls, so every module field rendered blank on a product that is fully
        // configured — and writing it would have created a second, broken set of rows
        // while the real provisioning settings sat untouched beside them.
        $this->moduleSettings = $p->settings->pluck('value', 'key')->all();
        $this->optionIds = $p->configOptions->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->upgradeIds = DB::table('product_upgrades')->where('product_id', $p->id)
            ->pluck('upgrade_id')->map(fn ($id) => (string) $id)->all();

        $this->crossSellIds = array_values(array_filter(explode(',', (string) ($meta['cross_sells'] ?? ''))));
    }

    // ── Details ─────────────────────────────────────────────────────────────────────

    public function saveDetails(): void
    {
        $this->validate([
            'form.category_id' => 'required|exists:categories,id',
            'form.name' => 'required|string|max:255',
            'form.slug' => 'required|string|max:255|unique:products,slug,' . $this->product->id,
            'form.description' => 'nullable|string|max:65535',
            'form.stock' => 'nullable|integer|min:0',
            'form.per_user_limit' => 'nullable|integer|min:0',
            'form.sort' => 'nullable|integer|min:0',
            // A database enum, not a flag. Casting it to int would have written 0 into a
            // column that only accepts disabled|separated|combined, and collapsed the
            // reference's three-way choice into a tick box on the way.
            'form.allow_quantity' => 'required|in:disabled,separated,combined',
            'extra.tagline' => 'nullable|string|max:255',
            'extra.short_description' => 'nullable|string|max:1000',
            'extra.type' => 'required|in:' . implode(',', array_keys(Meta::PRODUCT_TYPES)),
        ], attributes: [
            'form.category_id' => 'product group', 'form.name' => 'product name',
            'form.slug' => 'URL', 'form.stock' => 'stock',
        ]);

        $this->product->update([
            'category_id' => $this->form['category_id'],
            'name' => $this->form['name'],
            'slug' => $this->form['slug'] ?: Str::slug($this->form['name']),
            'description' => $this->form['description'] ?: null,
            // Stock Control off means unlimited, which core stores as NULL rather than 0 —
            // 0 would mean "none left" and stop the product being orderable at all.
            'stock' => $this->form['stock_enabled'] ? (int) $this->form['stock'] : null,
            'per_user_limit' => $this->form['per_user_limit'] !== '' ? (int) $this->form['per_user_limit'] : null,
            'allow_quantity' => $this->form['allow_quantity'],
            'email_template' => $this->form['email_template'] ?: null,
            'hidden' => (bool) $this->form['hidden'],
            'sort' => $this->form['sort'] !== '' ? (int) $this->form['sort'] : null,
        ]);

        foreach (['type', 'tagline', 'short_description', 'colour'] as $key) {
            Meta::put($this->product, $key, $this->extra[$key]);
        }

        Meta::put($this->product, 'featured', $this->extra['featured']);
        Meta::put($this->product, 'retired', $this->extra['retired']);

        $this->done('Product saved');
    }

    // ── Pricing ─────────────────────────────────────────────────────────────────────

    public function addPlan(): void
    {
        $this->plans[] = [
            'id' => null, 'name' => 'Monthly', 'type' => 'recurring',
            'billing_period' => 1, 'billing_unit' => 'month', 'prices' => [],
        ];
    }

    public function removePlan(int $index): void
    {
        $plan = $this->plans[$index] ?? null;

        if (!$plan) {
            return;
        }

        if ($plan['id']) {
            // Prices go with it; a price row whose plan is gone is unreachable and would
            // still be counted by anything summing the table.
            Price::where('plan_id', $plan['id'])->delete();
            Plan::whereKey($plan['id'])->delete();
        }

        unset($this->plans[$index]);
        $this->plans = array_values($this->plans);

        $this->done('Billing cycle removed');
    }

    public function savePricing(): void
    {
        $this->validate([
            'plans.*.name' => 'required|string|max:255',
            'plans.*.type' => 'required|in:free,one-time,recurring',
            'plans.*.billing_period' => 'required|integer|min:1',
            'plans.*.billing_unit' => 'required|in:day,week,month,year',
            'plans.*.prices.*.price' => 'nullable|numeric|min:0',
            'plans.*.prices.*.setup_fee' => 'nullable|numeric|min:0',
        ], attributes: ['plans.*.name' => 'cycle name', 'plans.*.billing_period' => 'billing period']);

        DB::transaction(function (): void {
            foreach ($this->plans as $row) {
                $plan = $row['id']
                    ? Plan::find($row['id'])
                    : new Plan(['priceable_type' => Product::class, 'priceable_id' => $this->product->id]);

                if (!$plan) {
                    continue;
                }

                $plan->fill([
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'billing_period' => (int) $row['billing_period'],
                    'billing_unit' => $row['billing_unit'],
                ]);

                $plan->priceable_type = Product::class;
                $plan->priceable_id = $this->product->id;
                $plan->save();

                foreach ($row['prices'] as $currency => $figures) {
                    Price::updateOrCreate(
                        ['plan_id' => $plan->id, 'currency_code' => $currency],
                        [
                            'price' => (float) ($figures['price'] ?: 0),
                            'setup_fee' => (float) ($figures['setup_fee'] ?: 0),
                        ],
                    );
                }
            }
        });

        $this->done('Pricing saved');
    }

    // ── Module, options, upgrades ───────────────────────────────────────────────────

    public function saveModule(): void
    {
        $this->product->update(['server_id' => $this->form['server_id'] ?: null]);

        foreach ($this->moduleSettings as $key => $value) {
            $this->product->settings()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => 'string'],
            );
        }

        $this->done('Module settings saved');
    }

    public function saveOptions(): void
    {
        DB::table('config_option_products')->where('product_id', $this->product->id)->delete();

        foreach (array_unique($this->optionIds) as $id) {
            DB::table('config_option_products')->insert([
                'config_option_id' => (int) $id,
                'product_id' => $this->product->id,
            ]);
        }

        $this->done('Configurable options saved');
    }

    public function saveUpgrades(): void
    {
        DB::table('product_upgrades')->where('product_id', $this->product->id)->delete();

        foreach (array_unique($this->upgradeIds) as $id) {
            // A product upgrading to itself would offer the customer a no-op that still
            // raises an invoice.
            if ((int) $id === $this->product->id) {
                continue;
            }

            DB::table('product_upgrades')->insert([
                'product_id' => $this->product->id,
                'upgrade_id' => (int) $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->done('Upgrades saved');
    }

    /**
     * The reference's Cross-sells: products recommended while ordering this one.
     *
     * Stored as a list of ids on the product, and read by the storefront's product page —
     * so ticking a box here changes what a customer is shown, rather than being remembered
     * and ignored.
     */
    public function saveCrossSells(): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->crossSellIds),
            fn (int $id): bool => $id > 0 && $id !== $this->product->id,
        )));

        Meta::put($this->product, 'cross_sells', implode(',', $ids));

        $this->done('Cross-sells saved');
    }

    private function done(string $message): void
    {
        $this->product->refresh()->load(['plans.prices', 'configOptions', 'settings', 'server']);
        $this->load();

        Notification::make()->title($message)->success()->send();
    }

    protected function getViewData(): array
    {
        $server = $this->product->server;

        $moduleFields = [];

        if ($server) {
            try {
                $moduleFields = ExtensionHelper::getProductConfig($server, $this->moduleSettings) ?: [];
            } catch (\Throwable $exception) {
                // A module that cannot be reached must not take the whole page with it —
                // the other five tabs have nothing to do with it.
                $moduleFields = [];
            }
        }

        // Built from the named routes rather than assembled by hand: the checkout link was
        // written as `/checkout?product=slug`, which looked reasonable and 404'd. Asking
        // the router means these cannot drift from what the storefront actually serves.
        $categorySlug = $this->product->category?->slug;

        $links = ['product' => null, 'group' => null, 'checkout' => null];

        if ($categorySlug) {
            try {
                $links = [
                    'product' => route('products.show', ['category' => $categorySlug, 'product' => $this->product->slug]),
                    'group' => route('category.show', ['category' => $categorySlug]),
                    'checkout' => route('products.checkout', ['category' => $categorySlug, 'product' => $this->product->slug]),
                ];
            } catch (\Throwable $exception) {
                // A product with no category yet has no storefront URL to show.
            }
        }

        return [
            'groups' => Category::orderBy('name')->get(['id', 'name']),
            'servers' => Server::orderBy('name')->get(['id', 'name', 'extension']),
            'currencies' => Currency::orderBy('code')->pluck('code')->all(),
            'optionGroups' => \App\Models\ConfigOption::whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'otherProducts' => Product::whereKeyNot($this->product->id)->orderBy('name')->get(['id', 'name']),
            'moduleFields' => $moduleFields,
            'links' => $links,
        ];
    }
}
