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
use Paymenter\Extensions\Others\TermLimits\Models\ProductTerm;

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
 * - **Configurable Options**, **Upgrades**, **Cross-sells**, **Links** —
 *   `config_option_products`, `product_upgrades`, a meta list, and the storefront URLs
 *   derived from the slug.
 * - **Custom Fields** — the reference's per-product field, asked on the order form and
 *   carried onto the service, is a config option here: named, typed, optionally with a
 *   list of choices, and attached to this product alone. Adding one from this tab creates
 *   it and assigns it; removing one detaches it, and deletes it only when no other product
 *   and no live service still hold it.
 *
 * ## The tabs that are not
 *
 * - **Free Domain** — this deployment sells proxies and domains are switched off; see
 *   `docs/10-disable-domains.md`. Every control on that tab would be inert.
 * - **Other** — its contents are affiliate payout overrides, subdomain options and overage
 *   billing, none of which exist here. The two parts that do — per-user limit and the
 *   product's sort position — are on it, beside a note naming the rest.
 *
 * Where a single control has nothing behind it — Server Group, three of the four auto-setup
 * choices, Upgrade Email, Require Domain, Apply Tax — it is still drawn, disabled, with a
 * title saying why. The tab then reads as the target does without pretending to act.
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

    /**
     * The reference's billing cycles, as columns of the Pricing grid.
     *
     * Each is a Paymenter plan: a type, a period and a unit. Ticking Enable creates the
     * plan; clearing it removes the plan and its prices. The names and the order are the
     * reference's own, so the grid reads the same way.
     */
    public const CYCLES = [
        'onetime' => ['label' => 'One Time', 'type' => 'one-time', 'period' => 1, 'unit' => 'month'],
        'monthly' => ['label' => 'Monthly', 'type' => 'recurring', 'period' => 1, 'unit' => 'month'],
        'quarterly' => ['label' => 'Quarterly', 'type' => 'recurring', 'period' => 3, 'unit' => 'month'],
        'semiannually' => ['label' => 'Semi-Annually', 'type' => 'recurring', 'period' => 6, 'unit' => 'month'],
        'annually' => ['label' => 'Annually', 'type' => 'recurring', 'period' => 1, 'unit' => 'year'],
        'biennially' => ['label' => 'Biennially', 'type' => 'recurring', 'period' => 2, 'unit' => 'year'],
        'triennially' => ['label' => 'Triennially', 'type' => 'recurring', 'period' => 3, 'unit' => 'year'],
    ];

    /** Which cycles this product is sold on: cycle key => bool. */
    public array $enabled = [];

    /** Figures per cycle and currency: [cycle][currency] => ['price' => …, 'setup_fee' => …]. */
    public array $pricing = [];

    /** The reference's Payment Type: free | one-time | recurring. */
    public string $paymentType = 'recurring';

    /**
     * The reference's Auto Terminate / Fixed Term and Termination Email.
     *
     * Both are real here: TermLimits' `ext_term_limit_products` was built for exactly
     * these two fields — days after activation, and which email announces the end.
     */
    public array $term = ['days' => 0, 'termination_email' => ''];

    /** Module fields declared by the server extension, as name => value. */
    public array $moduleSettings = [];

    /** Config option group ids ticked. */
    public array $optionIds = [];

    /** Product ids this one can be upgraded to. */
    public array $upgradeIds = [];

    /** Product ids recommended alongside this one — the reference's Cross-sells. */
    public array $crossSellIds = [];

    /**
     * The reference's Upgrades tab has a "Configurable Options" tick beside the package
     * list. Here that is `config_options.upgradable`, which core sets per option; this one
     * box applies the same answer to every option the product carries.
     */
    public bool $upgradeConfigOptions = false;

    /**
     * The reference's Add New Custom Field form.
     *
     * A per-product field collected on the order form is a config option here: named,
     * typed, optionally with a list of choices, and attached to this product alone. The
     * reference's field types map onto core's own list.
     */
    public array $customField = [
        'name' => '', 'type' => 'text', 'description' => '',
        'env_variable' => '', 'allowed_values' => '', 'sort' => 0,
        'hidden' => false,
    ];

    /** The reference's field types, against the types core's config options accept. */
    public const FIELD_TYPES = [
        'text' => 'Text Box',
        'number' => 'Number',
        'select' => 'Drop Down',
        'radio' => 'Radio Buttons',
        'checkbox' => 'Tick Box',
    ];

    /** Types whose choices come from a list the admin writes. */
    public const FIELD_TYPES_WITH_CHOICES = ['select', 'radio'];

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

        // The grid is the reference's: cycles across, figures down. Each cycle is matched
        // to a plan by its type/period/unit rather than by name, because the name is free
        // text and two installs will not have written it the same way.
        $currencies = Currency::orderBy('code')->pluck('code')->all();

        $this->enabled = [];
        $this->pricing = [];

        foreach (self::CYCLES as $key => $cycle) {
            $plan = $this->planFor($cycle);
            $this->enabled[$key] = $plan !== null;

            foreach ($currencies as $code) {
                $price = $plan?->prices->firstWhere('currency_code', $code);

                $this->pricing[$key][$code] = [
                    'price' => $price ? number_format((float) $price->price, 2, '.', '') : '',
                    'setup_fee' => $price ? number_format((float) $price->setup_fee, 2, '.', '') : '',
                ];
            }
        }

        // Payment Type follows what the product actually sells on.
        $types = $p->plans->pluck('type')->unique();
        $this->paymentType = $types->contains('recurring') ? 'recurring'
            : ($types->contains('one-time') ? 'one-time' : ($types->contains('free') ? 'free' : 'recurring'));

        // `settings` is keyed by `key`, not `name`. Reading the wrong column returned all
        // nulls, so every module field rendered blank on a product that is fully
        // configured — and writing it would have created a second, broken set of rows
        // while the real provisioning settings sat untouched beside them.
        $this->moduleSettings = $p->settings->pluck('value', 'key')->all();
        $this->optionIds = $p->configOptions->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->upgradeIds = DB::table('product_upgrades')->where('product_id', $p->id)
            ->pluck('upgrade_id')->map(fn ($id) => (string) $id)->all();

        $this->crossSellIds = array_values(array_filter(explode(',', (string) ($meta['cross_sells'] ?? ''))));

        // Ticked when every option that *could* be upgradable already is, so the box
        // reports the product's real state rather than a remembered intention.
        $upgradable = $p->configOptions->whereIn('type', ['select', 'radio', 'slider']);
        $this->upgradeConfigOptions = $upgradable->isNotEmpty()
            && $upgradable->every(fn ($option): bool => (bool) $option->upgradable);

        // Auto Terminate lives in TermLimits, which may not be installed.
        $this->term = ['days' => 0, 'termination_email' => ''];

        if (class_exists(ProductTerm::class)) {
            try {
                $row = ProductTerm::where('product_id', $p->id)->first();
                $this->term = [
                    'days' => (int) ($row->days ?? 0),
                    'termination_email' => (string) ($row->termination_email ?? ''),
                ];
            } catch (\Throwable $exception) {
                // Extension present but unmigrated: the row simply has no term yet.
            }
        }
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

    /** The plan matching one cycle, matched on shape rather than on its free-text name. */
    private function planFor(array $cycle): ?Plan
    {
        return $this->product->plans->first(
            fn (Plan $plan): bool => $plan->type === $cycle['type']
                && (int) $plan->billing_period === $cycle['period']
                && $plan->billing_unit === $cycle['unit'],
        );
    }

    /**
     * Write the grid back: a ticked cycle exists with its prices, an unticked one does not.
     *
     * Removing a cycle deletes its plan and prices. That is what unticking Enable means on
     * the reference, and leaving an orphaned plan behind would keep the product orderable
     * on a cycle the admin has just withdrawn.
     */
    public function savePricing(): void
    {
        $this->validate([
            'paymentType' => 'required|in:free,one-time,recurring',
            'term.days' => 'nullable|integer|min:0|max:65535',
            'term.termination_email' => 'nullable|string|max:255',
            'pricing.*.*.price' => 'nullable|numeric|min:0',
            'pricing.*.*.setup_fee' => 'nullable|numeric|min:0',
        ], attributes: ['paymentType' => 'payment type']);

        DB::transaction(function (): void {
            foreach (self::CYCLES as $key => $cycle) {
                $plan = $this->planFor($cycle);
                $wanted = (bool) ($this->enabled[$key] ?? false);

                if (!$wanted) {
                    if ($plan) {
                        Price::where('plan_id', $plan->id)->delete();
                        $plan->delete();
                    }

                    continue;
                }

                if (!$plan) {
                    $plan = new Plan([
                        'name' => $cycle['label'],
                        'type' => $cycle['type'],
                        'billing_period' => $cycle['period'],
                        'billing_unit' => $cycle['unit'],
                    ]);
                    $plan->priceable_type = Product::class;
                    $plan->priceable_id = $this->product->id;
                    $plan->save();
                }

                foreach (($this->pricing[$key] ?? []) as $currency => $figures) {
                    Price::updateOrCreate(
                        ['plan_id' => $plan->id, 'currency_code' => $currency],
                        [
                            'price' => (float) ($figures['price'] ?: 0),
                            'setup_fee' => (float) ($figures['setup_fee'] ?: 0),
                        ],
                    );
                }
            }

            // Allow Multiple Quantities lives on this tab in the reference, not on Details.
            $this->product->update(['allow_quantity' => $this->form['allow_quantity']]);

            // Auto Terminate / Termination Email. 0 days is the reference's "off", and the
            // row is kept rather than deleted so turning it off and on again does not lose
            // the email template beside it.
            if (class_exists(ProductTerm::class)) {
                ProductTerm::updateOrCreate(
                    ['product_id' => $this->product->id],
                    [
                        'days' => max(0, (int) $this->term['days']),
                        'termination_email' => $this->term['termination_email'] ?: null,
                    ],
                );
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
        // The reference's "Configurable Options" tick, applied to the options this product
        // actually carries. Only select/radio/slider can be upgraded — a text box has no
        // second choice to move to — so the others are left alone.
        \App\Models\ConfigOption::whereIn('id', $this->product->configOptions->pluck('id'))
            ->whereIn('type', ['select', 'radio', 'slider'])
            ->update(['upgradable' => $this->upgradeConfigOptions]);

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

    // ── Custom Fields ───────────────────────────────────────────────────────────────

    /**
     * Add one of the reference's custom fields to this product.
     *
     * It becomes a config option attached to this product alone: the same thing the
     * reference means by a per-product field, asked on the order form and carried onto the
     * service. A field with choices gets one child option per line.
     */
    public function saveCustomField(): void
    {
        $this->validate([
            'customField.name' => 'required|string|max:255',
            'customField.type' => 'required|in:' . implode(',', array_keys(self::FIELD_TYPES)),
            'customField.description' => 'nullable|string|max:255',
            'customField.env_variable' => 'nullable|string|max:255|regex:/^[A-Za-z_][A-Za-z0-9_]*$/',
            'customField.sort' => 'nullable|integer|min:0|max:255',
            'customField.allowed_values' => 'nullable|string|max:2000',
        ], [
            'customField.env_variable.regex' => 'The variable name may hold letters, numbers and underscores only.',
        ], attributes: [
            'customField.name' => 'field name', 'customField.type' => 'field type',
            'customField.allowed_values' => 'select options',
        ]);

        $type = $this->customField['type'];
        $choices = array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n|,/', (string) $this->customField['allowed_values']) ?: [],
        ), fn (string $line): bool => $line !== ''));

        if (in_array($type, self::FIELD_TYPES_WITH_CHOICES, true) && $choices === []) {
            $this->addError('customField.allowed_values', 'A drop down or radio field needs at least one choice.');

            return;
        }

        DB::transaction(function () use ($type, $choices): void {
            // The variable name is what the module reads the answer back under, so it has
            // to exist and be unique even when the admin leaves the box empty.
            $variable = $this->customField['env_variable']
                ?: Str::upper(Str::snake(Str::ascii($this->customField['name'])));

            $option = \App\Models\ConfigOption::create([
                'name' => $this->customField['name'],
                'description' => $this->customField['description'] ?: null,
                'env_variable' => $variable,
                'type' => $type,
                'sort' => (int) ($this->customField['sort'] ?: 0),
                'hidden' => (bool) $this->customField['hidden'],
            ]);

            // A tick box is one child in core's shape; a drop down or radio is one per line.
            $children = $type === 'checkbox' ? [$this->customField['name']] : $choices;

            foreach ($children as $index => $label) {
                \App\Models\ConfigOption::create([
                    'name' => $label,
                    'env_variable' => Str::upper(Str::snake(Str::ascii($label))) ?: 'OPTION_' . ($index + 1),
                    'parent_id' => $option->id,
                    'sort' => $index,
                ]);
            }

            DB::table('config_option_products')->insert([
                'config_option_id' => $option->id,
                'product_id' => $this->product->id,
            ]);
        });

        $this->customField = [
            'name' => '', 'type' => 'text', 'description' => '',
            'env_variable' => '', 'allowed_values' => '', 'sort' => 0, 'hidden' => false,
        ];

        $this->done('Custom field added');
    }

    /**
     * Detach a custom field from this product.
     *
     * The option itself is only deleted when no other product uses it — another product
     * sharing the field would lose it, and every service already answering it would lose
     * the answer with it.
     */
    public function deleteCustomField(int $id): void
    {
        $option = \App\Models\ConfigOption::find($id);

        if (!$option || !$this->product->configOptions->contains('id', $id)) {
            return;
        }

        DB::transaction(function () use ($option): void {
            DB::table('config_option_products')
                ->where('config_option_id', $option->id)
                ->where('product_id', $this->product->id)
                ->delete();

            $stillUsed = DB::table('config_option_products')
                ->where('config_option_id', $option->id)->exists();

            if (!$stillUsed && !$option->serviceConfigs()->exists()) {
                $option->children()->delete();
                $option->delete();
            }
        });

        $this->done('Custom field removed');
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
            // The fields already asked for this product, newest last, with their choices.
            'customFields' => $this->product->configOptions()
                ->with('children:id,parent_id,name')
                ->orderBy('sort')->orderBy('config_options.name')
                ->get(['config_options.id', 'config_options.name', 'config_options.description',
                    'config_options.env_variable', 'config_options.type', 'config_options.sort',
                    'config_options.hidden']),
            'otherProducts' => Product::whereKeyNot($this->product->id)->orderBy('name')->get(['id', 'name']),
            'moduleFields' => $moduleFields,
            'urlPrefix' => rtrim((string) config('app.url'), '/') . '/products/'
                . ($this->product->category?->slug ?? '') . '/',
            // Real notification templates, so Welcome Email is a choice rather than a key
            // to mistype. Keyed by the same `key` the column stores.
            'emailTemplates' => \App\Models\NotificationTemplate::orderBy('key')
                ->pluck('key', 'key')->all(),
            'links' => $links,
        ];
    }
}
